<?php

namespace App;

use App\Events\KitchenChanged;
use App\Models\Employee;
use App\Models\KitchenCancellation;
use App\Models\KitchenDispatch;
use App\Models\KitchenEvent;
use App\Models\KitchenItem;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderRound;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KitchenService
{
    public function dispatchRound(OrderRound $round): KitchenDispatch
    {
        return DB::transaction(function () use ($round): KitchenDispatch {
            $round = OrderRound::query()->with(['order.table.zone', 'lines.modifiers', 'lines.product.kitchenStation'])->lockForUpdate()->findOrFail($round->id);
            $dispatch = KitchenDispatch::query()->where('order_round_id', $round->id)->first();
            if ($dispatch) {
                return $dispatch->load('items');
            }
            $dispatch = KitchenDispatch::create(['restaurant_id' => $round->restaurant_id, 'order_id' => $round->order_id, 'order_round_id' => $round->id, 'status' => 'in_progress', 'fulfillment_label' => $round->order->fulfillmentLabel(), 'channel' => $round->order->channel, 'submitted_at' => $round->submitted_at ?? now()]);
            foreach ($round->lines as $line) {
                $station = $line->product?->kitchenStation;
                KitchenItem::create(['restaurant_id' => $round->restaurant_id, 'kitchen_dispatch_id' => $dispatch->id, 'order_line_id' => $line->id, 'kitchen_station_id' => $station?->id, 'station_name' => $station?->name ?? 'Sin asignar', 'product_name' => $line->product_name, 'format_name' => $line->format_name, 'quantity' => $line->activeQuantity(), 'modifiers' => $line->modifiers->map(fn ($modifier) => ['group' => $modifier->group_name, 'option' => $modifier->option_name, 'quantity' => $modifier->quantity, 'instruction' => $modifier->instruction])->values()->all(), 'notes' => $line->notes, 'status' => $line->activeQuantity() > 0 ? 'queued' : 'cancelled', 'queued_at' => $round->submitted_at ?? now()]);
            }
            $this->event($dispatch, 'round_submitted', ['round_id' => $round->id]);
            try {
                app(PrintService::class)->enqueueKitchenJobs($dispatch->fresh('items'));
            } catch (\Throwable) {
            }

            return $dispatch->load('items');
        });
    }

    public function transition(KitchenItem $item, string $to, ?int $expectedVersion, Employee $employee, User $user): KitchenItem
    {
        return DB::transaction(function () use ($item, $to, $expectedVersion, $employee, $user): KitchenItem {
            $item = KitchenItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($expectedVersion !== null && $item->version !== $expectedVersion) {
                throw new InvalidArgumentException('La línea de cocina ha cambiado.');
            }
            $allowed = ['queued' => ['preparing', 'ready', 'cancelled'], 'preparing' => ['ready', 'cancelled'], 'ready' => ['served', 'preparing', 'cancelled'], 'served' => [], 'cancelled' => []];
            if (! in_array($to, $allowed[$item->status] ?? [], true)) {
                throw new InvalidArgumentException('La transición de cocina no es válida.');
            }
            $data = ['status' => $to, 'version' => $item->version + 1];
            if ($to === 'preparing' && ! $item->started_at) {
                $data['started_at'] = now();
            } if ($to === 'ready' && ! $item->ready_at) {
                $data['ready_at'] = now();
            } if ($to === 'served' && ! $item->served_at) {
                $data['served_at'] = now();
            } $item->update($data);
            $this->refreshDispatch($item->dispatch()->lockForUpdate()->first(), $user, $employee);
            $order = $item->dispatch()->with('order.fulfillment')->first()?->order;
            if ($order?->fulfillment) {
                $fulfillmentStatus = match ($to) {
                    'preparing' => 'preparing',
                    'ready' => $order->channel === 'takeaway' ? 'ready_for_pickup' : ($order->channel === 'delivery' ? 'ready_for_dispatch' : 'ready'),
                    'served' => $order->channel === 'dine_in' ? 'completed' : 'ready',
                    default => null,
                };
                if ($fulfillmentStatus) {
                    $order->fulfillment->update(['status' => $fulfillmentStatus, 'completed_at' => $fulfillmentStatus === 'completed' ? now() : $order->fulfillment->completed_at]);
                }
            }
            $this->event($item, 'item_'.$to, ['employee_id' => $employee->id]);

            return $item->fresh('dispatch');
        });
    }

    public function cancellation(OrderLine $line, int $quantity, string $reason, Employee $employee, User $user): void
    {
        DB::transaction(function () use ($line, $quantity, $reason, $employee, $user): void {
            $item = $line->kitchenItem()->lockForUpdate()->first();
            if (! $item) {
                return;
            } $remaining = $item->quantity - $item->cancelled_quantity;
            if ($quantity < 1 || $quantity > $remaining) {
                throw new InvalidArgumentException('La cancelación de cocina no es válida.');
            } $item->update(['cancelled_quantity' => $item->cancelled_quantity + $quantity, 'version' => $item->version + 1, 'status' => $quantity === $remaining ? 'cancelled' : $item->status]);
            $cancel = KitchenCancellation::create(['restaurant_id' => $item->restaurant_id, 'kitchen_item_id' => $item->id, 'quantity' => $quantity, 'reason' => $reason, 'status' => 'pending', 'raised_at' => now()]);
            $this->event($item, 'cancellation_raised', ['cancellation_id' => $cancel->id, 'quantity' => $quantity, 'reason' => $reason]);
            $this->refreshDispatch($item->dispatch, $user, $employee);
            try {
                app(PrintService::class)->enqueueKitchenVoid($line->fresh(['order.table.zone', 'product.kitchenStation']), $quantity, $reason);
            } catch (\Throwable) {
            }
        });
    }

    public function acknowledgeCancellation(KitchenCancellation $cancellation, Employee $employee, User $user): KitchenCancellation
    {
        return DB::transaction(function () use ($cancellation, $employee) {
            $cancellation = KitchenCancellation::query()->lockForUpdate()->findOrFail($cancellation->id);
            if ($cancellation->status === 'acknowledged') {
                return $cancellation;
            } $cancellation->update(['status' => 'acknowledged', 'acknowledged_by_employee_id' => $employee->id, 'acknowledged_at' => now()]);
            $this->event($cancellation->item, 'cancellation_acknowledged', ['cancellation_id' => $cancellation->id]);

            return $cancellation->fresh();
        });
    }

    public function feed(?Order $order = null, ?KitchenStation $station = null): array
    {
        $query = KitchenItem::query()->with(['dispatch.order.table.zone', 'cancellations'])->where('restaurant_id', auth()->user()->restaurants()->first()?->id)->whereIn('status', ['queued', 'preparing', 'ready']);
        if ($station) {
            $query->where('kitchen_station_id', $station->id);
        }

        return ['items' => $query->orderBy('queued_at')->get(), 'server_time' => now()->toISOString()];
    }

    private function refreshDispatch(KitchenDispatch $dispatch, User $user, Employee $employee): void
    {
        $items = $dispatch->items()->lockForUpdate()->get();
        $active = $items->filter(fn ($item) => $item->activeQuantity() > 0);
        if ($active->isEmpty()) {
            $status = 'cancelled';
        } elseif ($active->every(fn ($item) => in_array($item->status, ['ready', 'served', 'cancelled'], true))) {
            $status = 'room_ready';
        } elseif ($active->contains(fn ($item) => $item->status === 'preparing')) {
            $status = 'in_progress';
        } else {
            $status = 'in_progress';
        } $dispatch->update(['status' => $status, 'version' => $dispatch->version + 1, 'ready_at' => $status === 'room_ready' ? ($dispatch->ready_at ?? now()) : $dispatch->ready_at]);
        $this->event($dispatch, 'dispatch_'.$status, ['employee_id' => $employee->id]);
    }

    private function event($entity, string $type, array $data = []): void
    {
        $restaurantId = $entity->restaurant_id;
        $event = KitchenEvent::create(['restaurant_id' => $restaurantId, 'kitchen_station_id' => $entity instanceof KitchenItem ? $entity->kitchen_station_id : null, 'entity_type' => class_basename($entity), 'entity_id' => $entity->id, 'type' => $type, 'data' => $data]);
        KitchenChanged::dispatch($restaurantId, $event->id);
    }
}
