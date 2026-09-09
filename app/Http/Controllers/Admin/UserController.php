<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAudit;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with('restaurants')->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'restaurants' => Restaurant::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'restaurant_id' => ['required', 'exists:restaurants,id'],
            'role' => ['required', Rule::in(['owner', 'member'])],
        ]);

        // is_platform_owner nunca proviene del request: ningún formulario
        // de administración puede crear ni promocionar propietarios globales.
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        // is_active/is_platform_owner no son mass-assignable a propósito.
        $user->forceFill(['is_active' => true])->save();
        $user->restaurants()->attach($data['restaurant_id'], ['role' => $data['role']]);

        $restaurant = Restaurant::query()->find($data['restaurant_id']);
        PlatformAudit::record($request->user(), 'user.created', $restaurant, $user->email.' · rol '.$data['role']);

        return redirect()->route('admin.users.edit', $user)->with('status', 'Usuario creado y asignado.');
    }

    public function edit(User $user): View
    {
        $user->load('restaurants');

        return view('admin.users.edit', [
            'user' => $user,
            'restaurants' => Restaurant::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->getKey())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            ...(($data['password'] ?? null) ? ['password' => $data['password']] : []),
        ]);

        PlatformAudit::record($request->user(), 'user.updated', null, $user->email);

        return back()->with('status', 'Usuario actualizado.');
    }

    public function attachMembership(Request $request, User $user): RedirectResponse
    {
        $this->guardOwnerMemberships($user);

        $data = $request->validate([
            'restaurant_id' => ['required', 'exists:restaurants,id'],
            'role' => ['required', Rule::in(['owner', 'member'])],
        ]);

        abort_if($user->restaurants()->whereKey($data['restaurant_id'])->exists(), 422, 'El usuario ya pertenece a ese restaurante.');

        $user->restaurants()->attach($data['restaurant_id'], ['role' => $data['role']]);

        PlatformAudit::record($request->user(), 'membership.attached', Restaurant::query()->find($data['restaurant_id']), $user->email.' · rol '.$data['role']);

        return back()->with('status', 'Acceso asignado.');
    }

    public function updateMembership(Request $request, User $user, Restaurant $restaurant): RedirectResponse
    {
        $this->guardOwnerMemberships($user);

        abort_unless($user->restaurants()->whereKey($restaurant->getKey())->exists(), 404);

        $data = $request->validate(['role' => ['required', Rule::in(['owner', 'member'])]]);

        $user->restaurants()->updateExistingPivot($restaurant->getKey(), ['role' => $data['role']]);

        PlatformAudit::record($request->user(), 'membership.role_changed', $restaurant, $user->email.' · rol '.$data['role']);

        return back()->with('status', 'Rol actualizado.');
    }

    /**
     * Quitar acceso: elimina solo la membership. Empleados, comandas,
     * ventas, fichajes y auditoría del usuario permanecen intactos.
     */
    public function detachMembership(Request $request, User $user, Restaurant $restaurant): RedirectResponse
    {
        $this->guardOwnerMemberships($user);

        abort_unless($user->restaurants()->whereKey($restaurant->getKey())->exists(), 404);

        $user->restaurants()->detach($restaurant->getKey());

        PlatformAudit::record($request->user(), 'membership.detached', $restaurant, $user->email);

        return back()->with('status', 'Acceso retirado. El histórico se conserva.');
    }

    /**
     * Desactivar: la cuenta no puede iniciar sesión pero no se borra nada.
     * El propietario global nunca puede desactivarse desde esta interfaz.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isPlatformOwner(), 422, 'La cuenta del propietario de Marchando no puede desactivarse.');

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        PlatformAudit::record($request->user(), $user->is_active ? 'user.activated' : 'user.deactivated', null, $user->email);

        return back()->with('status', $user->is_active ? 'Usuario activado.' : 'Usuario desactivado. Su histórico se conserva.');
    }

    /**
     * Las memberships del propietario global no se tocan desde la UI normal:
     * su acceso no depende de ellas y así es imposible dejar Marchando
     * accidentalmente sin propietario operativo.
     */
    private function guardOwnerMemberships(User $user): void
    {
        abort_if($user->isPlatformOwner(), 422, 'Las asociaciones del propietario de Marchando no se gestionan desde aquí.');
    }
}
