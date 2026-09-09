<?php

namespace App;

use App\Models\Employee;
use App\Models\KitchenDispatch;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Restaurant;
use App\Models\SaleDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PrintService
{
    public const MAX_ATTEMPTS = 5;

    public function width(Printer $printer): int
    {
        return $printer->paper_width === 58 ? 32 : 48;
    }

    public function printersForStation($restaurant, int $stationId)
    {
        $station = KitchenStation::query()->where('restaurant_id', $restaurant->id)->find($stationId);
        if (! $station) {
            return collect();
        }

        return $station->printers()->where('printers.restaurant_id', $restaurant->id)->where('printers.is_active', true)->get();
    }

    public function printersForUse($restaurant, string $use)
    {
        return $restaurant->printers()->where('is_active', true)->get()->filter(fn ($printer) => $printer->serves($use))->values();
    }

    public function enqueue(string $kind, $restaurant, ?Printer $printer, array $payload, array $context = []): PrintJob
    {
        abort_unless(in_array($kind, PrintJob::KINDS, true), 422);
        if ($printer) {
            abort_unless($printer->restaurant_id === $restaurant->id, 404);
        }
        $reference = $context['reference'] ?? $kind.'-'.($context['subject_id'] ?? '').'-'.time().'-'.bin2hex(random_bytes(4));

        return DB::transaction(function () use ($kind, $restaurant, $printer, $payload, $context, $reference): PrintJob {
            $existing = PrintJob::query()->where('restaurant_id', $restaurant->id)->where('reference', $reference)->first();
            if ($existing) {
                return $existing;
            }

            return PrintJob::create([
                'restaurant_id' => $restaurant->id, 'printer_id' => $printer?->id,
                'order_id' => $context['order_id'] ?? null, 'sale_document_id' => $context['sale_document_id'] ?? null,
                'kind' => $kind, 'reference' => $reference, 'payload' => $payload, 'status' => 'pending',
                'is_reprint' => (bool) ($context['is_reprint'] ?? false),
                'created_by_user_id' => $context['user_id'] ?? null, 'created_by_employee_id' => $context['employee_id'] ?? null,
            ]);
        });
    }

    public function enqueueKitchenJobs(KitchenDispatch $dispatch): void
    {
        $dispatch->loadMissing(['order.table.zone', 'order.currentEmployee', 'items']);
        $restaurant = $dispatch->restaurant ?? Restaurant::query()->findOrFail($dispatch->restaurant_id);
        $byStation = $dispatch->items->groupBy(fn ($item) => $item->kitchen_station_id ?? 0);
        foreach ($byStation as $stationId => $items) {
            $printers = $stationId
                ? $this->printersForStation($restaurant, (int) $stationId)
                : $this->printersForUse($restaurant, 'kitchen');
            if ($printers->isEmpty()) {
                $printers = $this->printersForUse($restaurant, 'kitchen');
            }
            foreach ($printers as $printer) {
                $this->enqueue('kitchen_ticket', $restaurant, $printer, $this->kitchenPayload($dispatch, $items->values()->all(), $this->width($printer), false), [
                    'reference' => "kitchen-{$dispatch->id}-{$stationId}-{$printer->id}",
                    'order_id' => $dispatch->order_id,
                ]);
            }
        }
    }

    public function enqueueKitchenVoid(OrderLine $line, int $quantity, string $reason): void
    {
        $line->loadMissing(['order.table.zone', 'order.currentEmployee', 'product.kitchenStation']);
        $order = $line->order;
        $restaurant = $order->restaurant;
        $stationId = $line->product?->kitchen_station_id;
        $printers = $stationId ? $this->printersForStation($restaurant, (int) $stationId) : $this->printersForUse($restaurant, 'kitchen');
        if ($printers->isEmpty()) {
            $printers = $this->printersForUse($restaurant, 'kitchen');
        }
        foreach ($printers as $printer) {
            $width = $this->width($printer);
            $lines = [$this->center('*** ANULACION ***', $width), $this->row('Mesa', $order->fulfillmentLabel(), $width), $this->row('Hora', now($restaurant->timezone)->format('H:i'), $width), str_repeat('-', $width), "-{$quantity} x {$line->product_name}", 'Motivo: '.mb_substr($reason, 0, $width - 9), str_repeat('-', $width)];
            $this->enqueue('kitchen_void', $restaurant, $printer, ['lines' => $lines, 'cut' => true], [
                'reference' => "void-{$line->id}-{$quantity}-".md5($reason)."-{$printer->id}",
                'order_id' => $order->id,
            ]);
        }
    }

    public function enqueueCustomerTicket(Order $order, ?SaleDocument $document, $restaurant, ?User $user = null, ?Employee $employee = null, bool $isReprint = false): void
    {
        foreach ($this->printersForUse($restaurant, 'ticket') as $printer) {
            $this->enqueue('customer_ticket', $restaurant, $printer, $this->customerTicketPayload($order, $document, $this->width($printer), $isReprint), [
                'reference' => 'ticket-'.$order->id.'-'.$printer->id.($isReprint ? '-r'.time() : ''),
                'order_id' => $order->id, 'sale_document_id' => $document?->id,
                'is_reprint' => $isReprint, 'user_id' => $user?->id, 'employee_id' => $employee?->id,
            ]);
        }
    }

    public function testPrint(Printer $printer, User $user): PrintJob
    {
        $restaurant = $printer->restaurant;
        $width = $this->width($printer);
        $lines = [$this->center($restaurant->name, $width), $this->center('Prueba de impresion correcta', $width), str_repeat('-', $width), $this->row('Impresora', $printer->name, $width), $this->row('Fecha', now($restaurant->timezone)->format('d/m/Y H:i'), $width), $this->row('Papel', $printer->paper_width.' mm', $width), str_repeat('-', $width)];

        return $this->enqueue('test', $restaurant, $printer, ['lines' => $lines, 'cut' => true], ['reference' => 'test-'.$printer->id.'-'.time(), 'user_id' => $user->id]);
    }

    public function reprint(PrintJob $job, User $user, ?Employee $employee): PrintJob
    {
        abort_unless(in_array($job->status, ['printed', 'error'], true), 422, 'Solo se puede reimprimir un trabajo terminado.');
        $payload = $job->payload;
        $lines = $payload['lines'] ?? [];
        array_unshift($lines, $this->center('*** REIMPRESION ***', 48));
        $payload['lines'] = $lines;

        return DB::transaction(function () use ($job, $user, $employee, $payload): PrintJob {
            $copy = $this->enqueue($job->kind, $job->restaurant, $job->printer, $payload, [
                'reference' => $job->reference.'-r'.time(), 'order_id' => $job->order_id,
                'sale_document_id' => $job->sale_document_id, 'is_reprint' => true,
                'user_id' => $user->id, 'employee_id' => $employee?->id,
            ]);
            $job->order?->events()->create(['restaurant_id' => $job->restaurant_id, 'user_id' => $user->id, 'employee_id' => $employee?->id, 'type' => 'print_reprint', 'data' => ['job_id' => $job->id, 'copy_id' => $copy->id, 'printer' => $job->printer?->name]]);

            return $copy;
        });
    }

    public function markPrinted(PrintJob $job): PrintJob
    {
        return DB::transaction(function () use ($job): PrintJob {
            $job = PrintJob::query()->lockForUpdate()->findOrFail($job->id);
            $job->update(['status' => 'printed', 'printed_at' => now(), 'error' => null]);
            $job->printer?->update(['last_seen_at' => now(), 'status_note' => null]);

            return $job->fresh();
        });
    }

    public function markError(PrintJob $job, string $error): PrintJob
    {
        return DB::transaction(function () use ($job, $error): PrintJob {
            $job = PrintJob::query()->lockForUpdate()->findOrFail($job->id);
            $job->update(['status' => 'error', 'attempts' => $job->attempts + 1, 'claimed_at' => null, 'error' => mb_substr($error, 0, 500)]);

            return $job->fresh();
        });
    }

    public function retry(PrintJob $job): PrintJob
    {
        abort_unless($job->status === 'error', 422, 'Solo se reintentan trabajos en error.');
        if ($job->attempts >= self::MAX_ATTEMPTS) {
            throw new InvalidArgumentException('Se alcanzó el máximo de reintentos; usa Reimprimir para generar un trabajo nuevo.');
        }

        return DB::transaction(function () use ($job): PrintJob {
            $job = PrintJob::query()->lockForUpdate()->findOrFail($job->id);
            $job->update(['status' => 'pending', 'error' => null]);

            return $job->fresh();
        });
    }

    public function kitchenPayload(KitchenDispatch $dispatch, array $items, int $width, bool $isReprint): array
    {
        $order = $dispatch->order;
        $lines = [];
        if ($isReprint) {
            $lines[] = $this->center('*** REIMPRESION ***', $width);
        }
        $lines[] = $this->center(mb_strtoupper($order->fulfillmentLabel()), $width);
        $lines[] = $this->row('Hora', $dispatch->submitted_at?->setTimezone($order->restaurant->timezone)->format('H:i') ?? '-', $width);
        $lines[] = $this->row('Camarero', $order->currentEmployee?->display_name ?? '-', $width);
        $lines[] = str_repeat('-', $width);
        foreach ($items as $item) {
            $lines[] = $item->quantity.' x '.mb_strtoupper($item->product_name ?? '');
            if ($item->format_name) {
                $lines[] = '  '.$item->format_name;
            }
            foreach ($item->modifiers ?? [] as $modifier) {
                $lines[] = '  + '.($modifier['quantity'] ?? 1).' x '.mb_strtoupper($modifier['option'] ?? '');
                if (! empty($modifier['instruction']) && $modifier['instruction'] !== 'normal') {
                    $lines[] = '    ('.$modifier['instruction'].')';
                }
            }
            if ($item->notes) {
                $lines[] = '  ** '.mb_strtoupper(mb_substr($item->notes, 0, 120));
            }
        }
        $lines[] = str_repeat('-', $width);

        return ['lines' => $lines, 'cut' => true];
    }

    public function customerTicketPayload(Order $order, ?SaleDocument $document, int $width, bool $isReprint): array
    {
        $order->loadMissing(['lines.modifiers', 'payments.tenders.method', 'table', 'customer', 'discounts', 'charges']);
        $restaurant = $order->restaurant;
        $lines = [$this->center($restaurant->name, $width)];
        if ($isReprint) {
            $lines[] = $this->center('*** REIMPRESION ***', $width);
        }
        if ($document) {
            $lines[] = $this->center(($document->kind === 'invoice' ? 'Factura ' : 'Ticket ').$document->reference, $width);
        }
        $lines[] = $this->row('Fecha', ($order->paid_at ?? $order->created_at)?->setTimezone($restaurant->timezone)->format('d/m/Y H:i') ?? '-', $width);
        $lines[] = $this->row('Mesa/Origen', $order->fulfillmentLabel(), $width);
        $lines[] = str_repeat('-', $width);
        foreach ($order->lines as $line) {
            if ($line->activeQuantity() < 1) {
                continue;
            }
            $lines[] = $this->row($line->activeQuantity().' x '.$line->product_name, CatalogMoney::format($line->active_line_total_minor).' '.$order->currency, $width);
        }
        $lines[] = str_repeat('-', $width);
        $discount = (int) $order->discounts()->where('is_active', true)->value('discount_minor');
        if ($discount > 0) {
            $lines[] = $this->row('Descuento', '-'.CatalogMoney::format($discount).' '.$order->currency, $width);
        }
        foreach ($order->charges as $charge) {
            $lines[] = $this->row($charge->label, CatalogMoney::format($charge->amount_minor).' '.$order->currency, $width);
        }
        $lines[] = $this->row('TOTAL', CatalogMoney::format($order->total_minor).' '.$order->currency, $width);
        foreach ($order->payments->where('status', 'succeeded') as $payment) {
            foreach ($payment->tenders as $tender) {
                $lines[] = $this->row($tender->method?->name ?? 'Pago', CatalogMoney::format($tender->amount_minor).' '.$order->currency, $width);
            }
        }
        if ($restaurant->ticket_footer) {
            $lines[] = $this->center(mb_substr($restaurant->ticket_footer, 0, 200), $width);
        }

        return ['lines' => $lines, 'cut' => true];
    }

    public function center(string $text, int $width): string
    {
        $text = mb_substr($text, 0, $width);
        $pad = max(0, intdiv($width - mb_strlen($text), 2));

        return str_repeat(' ', $pad).$text;
    }

    public function row(string $left, string $right, int $width): string
    {
        $left = mb_substr($left, 0, $width);
        $right = mb_substr($right, 0, $width);
        $space = max(1, $width - mb_strlen($left) - mb_strlen($right));

        return $left.str_repeat(' ', $space).$right;
    }
}
