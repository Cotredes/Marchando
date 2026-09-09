<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAudit;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RestaurantController extends Controller
{
    public function index(): View
    {
        $restaurants = Restaurant::query()->withCount(['users', 'diningTables', 'employees', 'orders'])->orderBy('name')->get();

        return view('admin.restaurants.index', compact('restaurants'));
    }

    public function create(): View
    {
        return view('admin.restaurants.form', ['restaurant' => new Restaurant(['timezone' => 'Europe/Madrid', 'currency' => 'EUR'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:restaurants,slug'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $restaurant = Restaurant::query()->create([
            'name' => $data['name'],
            'slug' => ($data['slug'] ?? '') !== '' ? $data['slug'] : Str::slug($data['name']),
            'timezone' => $data['timezone'],
            'currency' => strtoupper($data['currency']),
            'is_active' => true,
        ]);

        PlatformAudit::record($request->user(), 'restaurant.created', $restaurant, $restaurant->name);

        return redirect()->route('admin.restaurants.edit', $restaurant)->with('status', 'Restaurante creado. Complétalo desde sus módulos.');
    }

    public function edit(Restaurant $restaurant): View
    {
        return view('admin.restaurants.form', compact('restaurant'));
    }

    public function update(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:restaurants,slug,'.$restaurant->getKey()],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3'],
        ]);
        $data['currency'] = strtoupper($data['currency']);

        $restaurant->update($data);

        PlatformAudit::record($request->user(), 'restaurant.updated', $restaurant, $restaurant->name);

        return back()->with('status', 'Restaurante actualizado.');
    }

    /**
     * Activar/desactivar. No existe borrado de restaurantes: desactivar
     * conserva todo el histórico y bloquea la operación pública y el
     * acceso de sus miembros (el propietario sigue pudiendo entrar).
     */
    public function toggle(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update(['is_active' => ! $restaurant->is_active]);

        PlatformAudit::record(
            $request->user(),
            $restaurant->is_active ? 'restaurant.activated' : 'restaurant.deactivated',
            $restaurant,
            $restaurant->name
        );

        return back()->with('status', $restaurant->is_active ? 'Restaurante activado.' : 'Restaurante desactivado. Se conserva todo su histórico.');
    }

    /**
     * Entrar en el restaurante: redirige al panel del tenant. El cambio de
     * contexto es explícito por navegación; el dashboard destino registra
     * el restaurante actual en sesión.
     */
    public function enter(Restaurant $restaurant): RedirectResponse
    {
        return redirect()->route('restaurant.dashboard', $restaurant);
    }
}
