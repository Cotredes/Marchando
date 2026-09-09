<?php

namespace App\Http\Controllers;

use App\AnalyticsService;
use App\AuditService;
use App\Models\Restaurant;
use App\SalesPeriod;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function dashboard(Restaurant $restaurant): View
    {
        $this->authorize('viewAnalytics', $restaurant);
        [$from, $to, $period] = SalesPeriod::resolve($restaurant, request());
        $data = app(AnalyticsService::class)->overview($restaurant, $from, $to, request()->only(['channel', 'employee_id']));

        return view('analytics.dashboard', ['restaurant' => $restaurant, 'from' => $from, 'to' => $to, 'period' => $period] + $data);
    }

    public function audit(Restaurant $restaurant): View
    {
        $this->authorize('viewAudit', $restaurant);
        $filters = request()->only(['from', 'to', 'employee_id', 'module', 'type']);
        $entries = app(AuditService::class)->entries($restaurant, $filters);

        return view('analytics.audit', [
            'restaurant' => $restaurant, 'entries' => $entries, 'filters' => $filters,
            'employees' => $restaurant->employees()->orderBy('display_name')->get(),
        ]);
    }

    public function exportSales(Restaurant $restaurant): StreamedResponse
    {
        $this->authorize('viewAnalytics', $restaurant);
        [$from, $to] = SalesPeriod::resolve($restaurant, request());
        $sales = app(AnalyticsService::class)->salesQuery($restaurant, $from, $to, request()->only(['channel', 'employee_id']))->with(['table', 'currentEmployee', 'customer'])->orderBy('paid_at')->get();

        return $this->csv('ventas.csv', ['venta', 'fecha', 'canal', 'origen', 'mesa', 'cliente', 'camarero', 'total'], $sales->map(fn ($sale) => [
            $sale->id, $sale->paid_at?->toDateTimeString(), $sale->channel, $sale->origin, $sale->table?->name,
            $sale->customer?->display_name, $sale->currentEmployee?->display_name, number_format($sale->total_minor / 100, 2, '.', ''),
        ]));
    }

    public function exportProducts(Restaurant $restaurant): StreamedResponse
    {
        $this->authorize('viewAnalytics', $restaurant);
        [$from, $to] = SalesPeriod::resolve($restaurant, request());
        $data = app(AnalyticsService::class)->overview($restaurant, $from, $to);
        $rows = collect($data['productsRevenue'])->map(fn ($row, $key) => [$row['name'], $row['qty'], number_format($row['revenue'] / 100, 2, '.', ''), $row['cost'] ? number_format($row['cost'] / 100, 2, '.', '') : 'sin coste']);

        return $this->csv('productos.csv', ['producto', 'unidades', 'facturacion', 'coste_estimado'], $rows->values());
    }

    public function exportPayments(Restaurant $restaurant): StreamedResponse
    {
        $this->authorize('viewAnalytics', $restaurant);
        [$from, $to] = SalesPeriod::resolve($restaurant, request());
        $data = app(AnalyticsService::class)->overview($restaurant, $from, $to);

        return $this->csv('pagos.csv', ['metodo', 'total'], $data['tenders']->map(fn ($row) => [$row->method, number_format($row->total / 100, 2, '.', '')]));
    }

    private function csv(string $name, array $head, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($head, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $head, ';');
            foreach ($rows as $row) {
                fputcsv($out, (array) $row, ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
