<?php

namespace App\Http\Controllers;

use App\CustomerService;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class CustomerController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('viewSales', $restaurant);
        $customers = $restaurant->customers()->withCount(['orders', 'saleDocuments'])
            ->when(request('q'), fn ($q) => $q->where(fn ($inner) => $inner
                ->where('display_name', 'like', '%'.request('q').'%')
                ->orWhere('phone', 'like', '%'.request('q').'%')
                ->orWhere('email', 'like', '%'.request('q').'%')
                ->orWhere('tax_id', 'like', '%'.request('q').'%')))
            ->orderBy('display_name')->paginate(25)->withQueryString();

        return view('analytics.customers', compact('restaurant', 'customers'));
    }

    public function show(Restaurant $restaurant, Customer $customer): View
    {
        $this->authorize('viewSales', $restaurant);
        abort_unless($customer->restaurant_id === $restaurant->id, 404);
        $orders = $customer->orders()->with('table')->where('status', 'paid')->latest('paid_at')->limit(20)->get();
        $lifetime = (int) $customer->orders()->where('status', 'paid')->sum('total_minor');
        $documents = $customer->saleDocuments()->with('order')->latest('issued_at')->limit(20)->get();
        $next = $customer->orders()->where('status', 'open')->orderBy('opened_at')->first();

        return view('analytics.customer', compact('restaurant', 'customer', 'orders', 'lifetime', 'documents', 'next'));
    }

    public function store(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('viewSales', $restaurant);
        $data = request()->validate([
            'display_name' => ['nullable', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'], 'notes' => ['nullable', 'string', 'max:1000'],
            'is_company' => ['sometimes', 'boolean'], 'legal_name' => ['nullable', 'string', 'max:200'],
            'tax_id' => ['nullable', 'string', 'max:40'], 'fiscal_address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:20'], 'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'], 'country' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            if (filled($data['tax_id'] ?? null)) {
                $conflict = $restaurant->customers()->where('tax_id', mb_strtoupper(trim($data['tax_id'])))->exists();
                if ($conflict) {
                    throw new InvalidArgumentException('Ese NIF/CIF ya pertenece a otro cliente.');
                }
            }
            $normalized = app(CustomerService::class)->normalizePhone($data['phone'] ?? null);
            if ($normalized && $restaurant->customers()->where('phone_normalized', $normalized)->exists()) {
                throw new InvalidArgumentException('Ese teléfono ya pertenece a otro cliente: reutilízalo desde el buscador.');
            }
            $customer = $restaurant->customers()->create([
                ...$data, 'phone_normalized' => $normalized,
                'tax_id' => filled($data['tax_id'] ?? null) ? mb_strtoupper(trim($data['tax_id'])) : null,
            ]);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['customer' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('restaurant.customers.show', [$restaurant, $customer])->with('status', 'Cliente creado.');
    }

    public function update(Restaurant $restaurant, Customer $customer): RedirectResponse
    {
        $this->authorize('viewSales', $restaurant);
        abort_unless($customer->restaurant_id === $restaurant->id, 404);
        $data = request()->validate([
            'display_name' => ['nullable', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'], 'notes' => ['nullable', 'string', 'max:1000'],
            'is_company' => ['sometimes', 'boolean'], 'legal_name' => ['nullable', 'string', 'max:200'],
            'tax_id' => ['nullable', 'string', 'max:40'], 'fiscal_address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:20'], 'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'], 'country' => ['nullable', 'string', 'max:100'],
        ]);
        $normalized = app(CustomerService::class)->normalizePhone($data['phone'] ?? null);
        if ($normalized && $restaurant->customers()->where('phone_normalized', $normalized)->whereKeyNot($customer->id)->exists()) {
            return back()->withErrors(['customer' => 'Ese teléfono ya pertenece a otro cliente.'])->withInput();
        }
        if (filled($data['tax_id'] ?? null) && $restaurant->customers()->where('tax_id', mb_strtoupper(trim($data['tax_id'])))->whereKeyNot($customer->id)->exists()) {
            return back()->withErrors(['customer' => 'Ese NIF/CIF ya pertenece a otro cliente.'])->withInput();
        }
        $customer->update([...$data, 'phone_normalized' => $normalized, 'tax_id' => filled($data['tax_id'] ?? null) ? mb_strtoupper(trim($data['tax_id'])) : null]);

        return back()->with('status', 'Cliente actualizado. Las facturas ya emitidas no cambian.');
    }

    public function attach(Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('viewSales', $restaurant);
        abort_unless($order->restaurant_id === $restaurant->id && $order->status === 'open', 404);
        $customer = request()->filled('customer_id')
            ? $restaurant->customers()->findOrFail(request()->integer('customer_id'))
            : $restaurant->customers()->where('phone_normalized', app(CustomerService::class)->normalizePhone((string) request('phone')))->firstOrFail();
        $order->update(['customer_id' => $customer->id, 'version' => $order->version + 1]);
        $order->events()->create(['restaurant_id' => $restaurant->id, 'user_id' => request()->user()->id, 'employee_id' => null, 'type' => 'customer_attached', 'data' => ['customer_id' => $customer->id, 'customer_name' => $customer->display_name]]);

        return back()->with('status', 'Cliente asociado a la cuenta.');
    }
}
