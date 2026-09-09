<?php

namespace App\Http\Controllers;

use App\AccountingExportService;
use App\Models\Restaurant;
use App\SalesPeriod;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingController extends Controller
{
    private function period(Restaurant $restaurant): array
    {
        $request = request();
        if ($request->filled(['from', 'to'])) {
            $from = CarbonImmutable::parse($request->input('from'), $restaurant->timezone)->startOfDay();
            $to = CarbonImmutable::parse($request->input('to'), $restaurant->timezone)->endOfDay();

            return [$from, $to];
        }
        [$from, $to] = SalesPeriod::resolve($restaurant, $request);

        return [$from, $to];
    }

    private function csv(array $export): StreamedResponse
    {
        return response()->streamDownload(function () use ($export): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $export['head'], ';');
            foreach ($export['rows'] as $row) {
                fputcsv($out, array_map(fn ($value) => (string) ($value ?? ''), $row), ';');
            }
            fclose($out);
        }, $export['filename'], ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function sales(Restaurant $restaurant): StreamedResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        [$from, $to] = $this->period($restaurant);

        return $this->csv(app(AccountingExportService::class)->sales($restaurant, $from, $to, request()->user()));
    }

    public function invoices(Restaurant $restaurant): StreamedResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        [$from, $to] = $this->period($restaurant);

        return $this->csv(app(AccountingExportService::class)->invoices($restaurant, $from, $to, request()->user()));
    }

    public function payments(Restaurant $restaurant): StreamedResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        [$from, $to] = $this->period($restaurant);

        return $this->csv(app(AccountingExportService::class)->payments($restaurant, $from, $to, request()->user()));
    }

    public function cash(Restaurant $restaurant): StreamedResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        [$from, $to] = $this->period($restaurant);

        return $this->csv(app(AccountingExportService::class)->cash($restaurant, $from, $to, request()->user()));
    }
}
