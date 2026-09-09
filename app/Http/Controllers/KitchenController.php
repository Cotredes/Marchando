<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreparationStationRequest;
use App\KitchenService;
use App\Models\Employee;
use App\Models\KitchenCancellation;
use App\Models\KitchenItem;
use App\Models\KitchenStation;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use InvalidArgumentException;

class KitchenController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('useKitchen', $restaurant);
        $stations = $restaurant->kitchenStations()->where('is_active', true)->get();
        $employees = $restaurant->employees()->with('operationalRoles')->where('is_active', true)->whereNotNull('pin_hash')->whereHas('operationalRoles', fn ($q) => $q->whereIn('code', ['kitchen', 'manager']))->orderBy('display_name')->get();
        $station = request()->integer('station');
        $items = KitchenItem::with(['dispatch.order.table.zone', 'cancellations'])->where('restaurant_id', $restaurant->id)->whereIn('status', ['queued', 'preparing', 'ready'])->when($station, fn ($q) => $q->where('kitchen_station_id', $station))->orderBy('queued_at')->get();

        return view('kitchen.index', compact('restaurant', 'stations', 'employees', 'items', 'station'));
    }

    public function identify(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('useKitchen', $restaurant);
        $employee = $restaurant->employees()->with('operationalRoles')->whereKey(request()->integer('employee_id'))->where('is_active', true)->first();
        if (! $employee || ! Hash::check((string) request('pin'), $employee->pin_hash) || ! $employee->operationalRoles->contains(fn ($role) => in_array($role->code, ['kitchen', 'manager'], true))) {
            return back()->withErrors(['pin' => 'No se pudo verificar el empleado de cocina.']);
        }
        session(['kds_employee_'.$restaurant->id => $employee->id]);

        return back()->with('status', 'Empleado de cocina identificado.');
    }

    public function feed(Restaurant $restaurant): JsonResponse
    {
        $this->authorize('useKitchen', $restaurant);
        $station = request()->integer('station');
        $items = KitchenItem::with(['dispatch.order.table.zone', 'cancellations'])->where('restaurant_id', $restaurant->id)->whereIn('status', ['queued', 'preparing', 'ready'])->when($station, fn ($q) => $q->where('kitchen_station_id', $station))->orderBy('queued_at')->get();

        return response()->json(['mode' => 'snapshot', 'cursor' => (int) $restaurant->kitchenEvents()->max('id'), 'server_time' => now()->toISOString(), 'items' => $items, 'stations' => $restaurant->kitchenStations()->where('is_active', true)->get()]);
    }

    public function transition(Restaurant $restaurant, KitchenItem $item): JsonResponse
    {
        $this->authorize('useKitchen', $restaurant);
        abort_unless($item->restaurant_id === $restaurant->id, 404);
        try {
            $employee = $this->employee($restaurant);
            $item = app(KitchenService::class)->transition($item, (string) request('to'), request()->has('expected_version') ? (int) request('expected_version') : null, $employee, request()->user());

            return response()->json(['item' => $item]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], $e instanceof InvalidArgumentException ? 422 : 500);
        }
    }

    public function acknowledge(Restaurant $restaurant, KitchenCancellation $cancellation): JsonResponse
    {
        $this->authorize('useKitchen', $restaurant);
        abort_unless($cancellation->restaurant_id === $restaurant->id, 404);
        try {
            $result = app(KitchenService::class)->acknowledgeCancellation($cancellation, $this->employee($restaurant), request()->user());

            return response()->json(['cancellation' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function manage(Restaurant $restaurant): View
    {
        $this->authorize('manageKitchen', $restaurant);

        return view('kitchen.manage', ['restaurant' => $restaurant, 'stations' => $restaurant->kitchenStations()->withTrashed()->get()]);
    }

    public function store(PreparationStationRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageKitchen', $restaurant);
        $restaurant->kitchenStations()->create([...$request->validated(), 'position' => ((int) $restaurant->kitchenStations()->max('position')) + 10]);

        return back()->with('status', 'Estación creada.');
    }

    public function update(PreparationStationRequest $request, Restaurant $restaurant, KitchenStation $station): RedirectResponse
    {
        $this->authorize('manage', $station);
        abort_unless($station->restaurant_id === $restaurant->id, 404);
        $station->update($request->validated());

        return back()->with('status', 'Estación actualizada.');
    }

    public function destroy(Restaurant $restaurant, KitchenStation $station): RedirectResponse
    {
        $this->authorize('manage', $station);
        abort_unless($station->restaurant_id === $restaurant->id, 404);
        if ($station->items()->exists()) {
            return back()->withErrors(['station' => 'No se puede archivar una estación con historial de cocina.']);
        }$station->delete();

        return back()->with('status', 'Estación archivada.');
    }

    private function employee(Restaurant $restaurant): Employee
    {
        $id = request('employee_id') ?: session('kds_employee_'.$restaurant->id);
        $employee = $restaurant->employees()->with('operationalRoles')->whereKey($id)->where('is_active', true)->first();
        if (! $employee || (request('pin') && ! Hash::check((string) request('pin'), $employee->pin_hash))) {
            throw new InvalidArgumentException('Empleado operativo no autorizado.');
        }if (! $employee->operationalRoles->contains(fn ($r) => in_array($r->code, ['kitchen', 'manager'], true))) {
            throw new InvalidArgumentException('Se requiere personal de cocina.');
        }session(['kds_employee_'.$restaurant->id => $employee->id]);

        return $employee;
    }
}
