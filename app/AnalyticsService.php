<?php

namespace App;

use App\Models\OnlineRefund;
use App\Models\Order;
use App\Models\PaymentReversal;
use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /** @return array<string, mixed> */
    public function overview(Restaurant $restaurant, CarbonImmutable $from, CarbonImmutable $to, array $filters = []): array
    {
        $sales = $this->salesQuery($restaurant, $from, $to, $filters);
        $saleIds = (clone $sales)->pluck('orders.id')->all();
        $orders = (clone $sales)->with(['table', 'currentEmployee', 'customer', 'discounts', 'payments.tenders.method', 'charges'])->orderBy('paid_at')->get();

        $revenue = (int) (clone $sales)->sum('orders.total_minor');
        $count = (clone $sales)->count();
        $guests = (clone $sales)->whereNotNull('orders.guest_count')->sum('orders.guest_count');
        $guestCoverage = $count > 0 ? (clone $sales)->whereNotNull('orders.guest_count')->count() : 0;

        $discountAgg = DB::table('order_discounts')
            ->join('orders', 'orders.id', '=', 'order_discounts.order_id')
            ->where('order_discounts.restaurant_id', $restaurant->id)
            ->where('order_discounts.is_active', true)
            ->whereIn('orders.id', $saleIds)
            ->selectRaw('COALESCE(SUM(order_discounts.discount_minor),0) as total, COUNT(*) as times')
            ->first();
        $voids = (int) DB::table('order_lines')
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('order_id', $saleIds)
            ->selectRaw('COALESCE(SUM(voided_quantity * COALESCE(manual_unit_total_minor, unit_total_minor)),0) as total')
            ->value('total');

        $byChannel = (clone $sales)
            ->selectRaw('orders.channel as channel, orders.origin as origin, COUNT(*) as sales, COALESCE(SUM(orders.total_minor),0) as revenue')
            ->groupBy('orders.channel', 'orders.origin')->get();
        $byEmployee = (clone $sales)
            ->leftJoin('employees', 'employees.id', '=', 'orders.current_employee_id')
            ->selectRaw('orders.current_employee_id as employee_id, COALESCE(employees.display_name, ?) as name, COUNT(*) as sales, COALESCE(SUM(orders.total_minor),0) as revenue', ['Sin asignar'])
            ->groupBy('orders.current_employee_id', 'employees.display_name')->get();

        $tenders = DB::table('payment_tenders')
            ->join('payments', 'payments.id', '=', 'payment_tenders.payment_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'payment_tenders.payment_method_id')
            ->where('payments.restaurant_id', $restaurant->id)
            ->whereIn('payments.order_id', $saleIds)
            ->where('payments.status', 'succeeded')
            ->selectRaw('payment_methods.name as method, payment_methods.is_cash as is_cash, COALESCE(SUM(payment_tenders.amount_minor),0) as total')
            ->groupBy('payment_methods.name', 'payment_methods.is_cash')->get();
        $cashTotal = (int) $tenders->where('is_cash', 1)->sum('total');
        $cardTotal = (int) $tenders->where('is_cash', 0)->sum('total');

        $lines = DB::table('order_lines')
            ->where('order_lines.restaurant_id', $restaurant->id)
            ->whereIn('order_lines.order_id', $saleIds)
            ->select(['order_lines.product_id', 'order_lines.product_name', 'order_lines.quantity', 'order_lines.voided_quantity', 'order_lines.unit_total_minor', 'order_lines.manual_unit_total_minor', 'order_lines.active_line_total_minor', 'order_lines.cost_minor', 'order_lines.snapshot'])
            ->get();
        $productsQty = [];
        $productsRevenue = [];
        $costKnownRevenue = 0;
        $costTotal = 0;
        $categories = [];
        foreach ($lines as $line) {
            $qty = max(0, $line->quantity - $line->voided_quantity);
            if ($qty < 1) {
                continue;
            }
            $key = $line->product_id ?? 'p-'.$line->product_name;
            $unit = $line->manual_unit_total_minor ?? $line->unit_total_minor;
            $revenue_line = $qty * $unit;
            $productsQty[$key] ??= ['name' => $line->product_name, 'qty' => 0];
            $productsQty[$key]['qty'] += $qty;
            $productsRevenue[$key] ??= ['name' => $line->product_name, 'revenue' => 0, 'qty' => 0, 'cost' => 0, 'cost_known' => 0];
            $productsRevenue[$key]['revenue'] += $revenue_line;
            $productsRevenue[$key]['qty'] += $qty;
            if ($line->cost_minor !== null) {
                $productsRevenue[$key]['cost'] += $qty * $line->cost_minor;
                $productsRevenue[$key]['cost_known'] += $revenue_line;
                $costKnownRevenue += $revenue_line;
                $costTotal += $qty * $line->cost_minor;
            }
            $snapshot = is_string($line->snapshot) ? json_decode($line->snapshot, true) : $line->snapshot;
            $category = $snapshot['product']['category']['name'] ?? 'Sin categoría';
            $categories[$category] ??= ['name' => $category, 'qty' => 0, 'revenue' => 0];
            $categories[$category]['qty'] += $qty;
            $categories[$category]['revenue'] += $revenue_line;
        }
        $productsQty = collect($productsQty)->sortByDesc('qty');
        $productsRevenue = collect($productsRevenue)->sortByDesc('revenue');
        $categories = collect($categories)->sortByDesc('revenue');

        $byDay = (clone $sales)
            ->selectRaw('DATE(orders.paid_at) as day, COUNT(*) as sales, COALESCE(SUM(orders.total_minor),0) as revenue')
            ->groupBy('day')->orderBy('day')->get();
        $byHour = (clone $sales)->get()->groupBy(fn ($order) => CarbonImmutable::parse($order->paid_at)->setTimezone($restaurant->timezone)->hour)
            ->map(fn ($group, $hour) => ['hour' => (int) $hour, 'sales' => $group->count(), 'revenue' => $group->sum('total_minor')])
            ->sortKeys()->values();

        $deliveryFees = (int) DB::table('order_charges')
            ->where('restaurant_id', $restaurant->id)->where('type', 'delivery')
            ->whereIn('order_id', $saleIds)->sum('amount_minor');

        $kitchen = $this->kitchenMetrics($restaurant, $from, $to);
        $previous = $this->previousComparison($restaurant, $from, $to, $filters, $revenue);
        $refundedTotal = (int) OnlineRefund::query()->where('restaurant_id', $restaurant->id)->where('status', 'succeeded')->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])->sum('amount_minor')
            + (int) PaymentReversal::query()->where('restaurant_id', $restaurant->id)->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])->sum('amount_minor');
        $netRevenue = max(0, $revenue - $refundedTotal);

        return compact('orders', 'revenue', 'count', 'guests', 'guestCoverage', 'discountAgg', 'voids', 'byChannel', 'byEmployee', 'tenders', 'cashTotal', 'cardTotal', 'productsQty', 'productsRevenue', 'categories', 'byDay', 'byHour', 'deliveryFees', 'costKnownRevenue', 'costTotal', 'kitchen', 'previous', 'refundedTotal', 'netRevenue');
    }

    public function salesQuery(Restaurant $restaurant, CarbonImmutable $from, CarbonImmutable $to, array $filters = [])
    {
        $query = Order::query()->where('orders.restaurant_id', $restaurant->id)
            ->where('orders.status', 'paid')
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$from->toDateTimeString(), $to->toDateTimeString()]);

        if (filled($filters['channel'] ?? null)) {
            $query->where('orders.channel', $filters['channel']);
        }
        if (filled($filters['employee_id'] ?? null)) {
            $query->where('orders.current_employee_id', $filters['employee_id']);
        }
        if (filled($filters['table_id'] ?? null)) {
            $query->where('orders.dining_table_id', $filters['table_id']);
        }
        if (filled($filters['customer_id'] ?? null)) {
            $query->where('orders.customer_id', $filters['customer_id']);
        }
        if (filled($filters['method'] ?? null)) {
            $method = $filters['method'];
            $query->whereHas('payments.tenders.method', fn ($q) => $method === 'cash' || $method === 'card'
                ? $q->where('is_cash', $method === 'cash')
                : $q->where('payment_methods.id', $method));
        }
        if (filled($filters['q'] ?? null)) {
            $q = trim($filters['q']);
            $query->where(fn ($inner) => $inner->where('orders.id', $q)
                ->orWhereHas('customer', fn ($c) => $c->where('display_name', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'))
                ->orWhereHas('table', fn ($t) => $t->where('name', 'like', '%'.$q.'%')));
        }

        return $query;
    }

    private function kitchenMetrics(Restaurant $restaurant, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = DB::table('kitchen_items')
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('queued_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->select(['queued_at', 'started_at', 'ready_at', 'kitchen_station_id'])->get();
        $toStart = [];
        $toReady = [];
        foreach ($items as $item) {
            if ($item->queued_at && $item->started_at) {
                $toStart[] = CarbonImmutable::parse($item->started_at)->diffInSeconds(CarbonImmutable::parse($item->queued_at));
            }
            if ($item->queued_at && $item->ready_at) {
                $toReady[] = CarbonImmutable::parse($item->ready_at)->diffInSeconds(CarbonImmutable::parse($item->queued_at));
            }
        }
        $avg = fn (array $values) => count($values) ? (int) round(array_sum($values) / count($values)) : null;

        return ['items' => count($items), 'avg_to_start' => $avg($toStart), 'avg_to_ready' => $avg($toReady)];
    }

    private function previousComparison(Restaurant $restaurant, CarbonImmutable $from, CarbonImmutable $to, array $filters, int $revenue): ?array
    {
        $length = $from->diffInSeconds($to);
        if ($length < 1) {
            return null;
        }
        $prevTo = $from;
        $prevFrom = $from->subSeconds($length);
        $previousRevenue = (int) $this->salesQuery($restaurant, $prevFrom, $prevTo, $filters)->sum('orders.total_minor');
        if ($previousRevenue < 1) {
            return null;
        }

        return ['revenue' => $previousRevenue, 'delta_pct' => round((($revenue - $previousRevenue) / $previousRevenue) * 100, 1)];
    }
}
