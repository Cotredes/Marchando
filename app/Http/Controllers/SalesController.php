<?php

namespace App\Http\Controllers;

use App\AnalyticsService;
use App\BillingService;
use App\CustomerService;
use App\Models\Restaurant;
use App\Models\SaleDocument;
use App\SalesPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class SalesController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('viewSales', $restaurant);
        [$from, $to] = SalesPeriod::resolve($restaurant, request());
        $filters = request()->only(['channel', 'employee_id', 'method', 'table_id', 'customer_id', 'q']);
        $sales = app(AnalyticsService::class)->salesQuery($restaurant, $from, $to, $filters)
            ->with(['table', 'currentEmployee', 'customer', 'saleDocuments'])
            ->orderByDesc('paid_at')->paginate(25)->withQueryString();

        return view('analytics.sales', [
            'restaurant' => $restaurant, 'sales' => $sales, 'from' => $from, 'to' => $to, 'filters' => $filters,
            'employees' => $restaurant->employees()->orderBy('display_name')->get(),
            'tables' => $restaurant->diningTables()->orderBy('name')->get(),
        ]);
    }

    public function show(Restaurant $restaurant, int $order): View
    {
        $this->authorize('viewSales', $restaurant);
        $sale = $restaurant->orders()->with(['lines.modifiers', 'payments.tenders.method', 'table.zone', 'customer', 'currentEmployee', 'openedByEmployee', 'discounts', 'charges', 'saleDocuments.customer', 'fulfillment'])->findOrFail($order);
        abort_unless($sale->status === 'paid', 404);
        try {
            app(BillingService::class)->ensureTicket($sale, request()->user(), null);
            $sale->load('saleDocuments');
        } catch (InvalidArgumentException) {
        }
        $totals = app(BillingService::class)->totals($sale);

        return view('analytics.sale', ['restaurant' => $restaurant, 'sale' => $sale, 'totals' => $totals]);
    }

    public function ticket(Restaurant $restaurant, int $order): View
    {
        $this->authorize('viewSales', $restaurant);
        $sale = $restaurant->orders()->findOrFail($order);
        abort_unless($sale->status === 'paid', 404);
        $document = app(BillingService::class)->ensureTicket($sale, request()->user(), null);

        return view('analytics.ticket', ['restaurant' => $restaurant, 'document' => $document->fresh()]);
    }

    public function invoices(Restaurant $restaurant): View
    {
        $this->authorize('viewSales', $restaurant);
        $invoices = $restaurant->saleDocuments()->with(['order', 'customer'])->where('kind', 'invoice')->orderByDesc('issued_at')->paginate(25);

        return view('analytics.invoices', compact('restaurant', 'invoices'));
    }

    public function invoiceShow(Restaurant $restaurant, SaleDocument $saleDocument): View
    {
        $this->authorize('viewSales', $restaurant);
        abort_unless($saleDocument->restaurant_id === $restaurant->id && $saleDocument->kind === 'invoice', 404);
        $fiscal = $saleDocument->fiscalRecords()->where('record_type', 'alta')->latest('id')->first();

        return view('analytics.invoice', ['restaurant' => $restaurant, 'document' => $saleDocument->load(['order', 'customer']), 'fiscal' => $fiscal]);
    }

    public function invoiceStore(Restaurant $restaurant, int $order): RedirectResponse
    {
        $this->authorize('manageBilling', $restaurant);
        $sale = $restaurant->orders()->with('customer')->findOrFail($order);
        try {
            $customer = $sale->customer;
            if (request()->filled('customer_id')) {
                $customer = $restaurant->customers()->findOrFail(request()->integer('customer_id'));
            }
            if (! $customer && request()->filled('tax_id')) {
                $customer = $restaurant->customers()->create(['display_name' => request()->input('display_name'), 'tax_id' => mb_strtoupper(trim((string) request()->input('tax_id')))]);
            }
            if (! $customer) {
                return back()->withErrors(['customer' => 'Selecciona un cliente o indica sus datos fiscales.']);
            }
            if (request()->filled('tax_id') || ! filled($customer->tax_id)) {
                $customer = app(CustomerService::class)->updateFiscal($restaurant, $customer, request()->only(['display_name', 'is_company', 'legal_name', 'tax_id', 'fiscal_address', 'postal_code', 'city', 'province', 'country', 'email', 'phone']), request()->user());
            }
            $document = app(BillingService::class)->createInvoice($sale, $customer, request()->user(), null);
            if (! $sale->customer_id) {
                $sale->update(['customer_id' => $customer->id]);
            }
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['invoice' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('restaurant.invoices.show', [$restaurant, $document])->with('status', 'Factura '.$document->reference.' emitida.');
    }
}
