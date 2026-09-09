<?php

namespace App\Http\Controllers;

use App\AttendanceService;
use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    private function roles(Restaurant $restaurant): void
    {
        foreach ([['manager', 'Encargado'], ['service', 'Camarero'], ['kitchen', 'Cocina'], ['delivery', 'Reparto']] as [$code, $name]) {
            $restaurant->operationalRoles()->firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    public function index(Restaurant $restaurant): View
    {
        $this->authorize('viewStaff', $restaurant);
        $this->roles($restaurant);
        $query = $restaurant->employees()->with('operationalRoles')->withCount(['workIntervals as open_intervals_count' => fn ($q) => $q->whereNull('ended_at')]);
        if (request('q')) {
            $query->where(fn ($q) => $q->where('display_name', 'like', '%'.request('q').'%')->orWhere('first_name', 'like', '%'.request('q').'%')->orWhere('last_name', 'like', '%'.request('q').'%'));
        }
        if (request('state') === 'active') {
            $query->where('is_active', true);
        } elseif (request('state') === 'inactive') {
            $query->where('is_active', false);
        }
        if (request('role')) {
            $query->whereHas('operationalRoles', fn ($q) => $q->whereKey(request('role')));
        }

        return view('staff.employees.index', ['restaurant' => $restaurant, 'employees' => $query->orderBy('display_name')->paginate(25)->withQueryString(), 'roles' => $restaurant->operationalRoles, 'canManage' => auth()->user()->can('manageStaff', $restaurant)]);
    }

    public function create(Restaurant $restaurant): View
    {
        $this->authorize('manageStaff', $restaurant);
        $this->roles($restaurant);

        return view('staff.employees.form', ['restaurant' => $restaurant, 'employee' => new Employee(['is_active' => true]), 'roles' => $restaurant->operationalRoles, 'users' => $restaurant->users()->orderBy('name')->get(), 'canManage' => true]);
    }

    public function store(EmployeeRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validated();
        $roles = $data['roles'];
        unset($data['roles'], $data['pin'], $data['remove_pin']);
        if ($request->pinWasProvided()) {
            $fingerprint = AttendanceService::fingerprint($restaurant->id, $request->input('pin'));
            abort_if($restaurant->employees()->where('is_active', true)->where('pin_fingerprint', $fingerprint)->exists(), 422, 'Ese PIN ya está asignado a otro empleado activo.');
            $data['pin_hash'] = Hash::make($request->input('pin'));
            $data['pin_fingerprint'] = ($data['is_active'] ?? true) ? $fingerprint : null;
        }
        $employee = $restaurant->employees()->create($data);
        $employee->operationalRoles()->sync($roles);

        return redirect()->route('restaurant.staff.employees.index', $restaurant)->with('status', 'Empleado creado.');
    }

    public function show(Restaurant $restaurant, Employee $employee): View
    {
        $this->authorize('view', $employee);
        abort_unless($employee->restaurant_id === $restaurant->id, 404);
        $intervals = $employee->workIntervals()->with('corrections')->latest('started_at')->paginate(20);

        return view('staff.employees.show', ['restaurant' => $restaurant, 'employee' => $employee, 'intervals' => $intervals, 'canManage' => auth()->user()->can('manageAttendance', $restaurant)]);
    }

    public function edit(Restaurant $restaurant, Employee $employee): View
    {
        $this->authorize('manage', $employee);
        abort_unless($employee->restaurant_id === $restaurant->id, 404);
        $this->roles($restaurant);

        return view('staff.employees.form', ['restaurant' => $restaurant, 'employee' => $employee->load('operationalRoles'), 'roles' => $restaurant->operationalRoles, 'users' => $restaurant->users()->orderBy('name')->get(), 'canManage' => true]);
    }

    public function update(EmployeeRequest $request, Restaurant $restaurant, Employee $employee): RedirectResponse
    {
        $this->authorize('manage', $employee);
        abort_unless($employee->restaurant_id === $restaurant->id, 404);
        if (! $request->boolean('is_active') && $employee->openInterval()->exists()) {
            return back()->withErrors(['is_active' => 'Este empleado tiene una jornada abierta. Corrígela antes de desactivarlo.']);
        }
        if ($request->boolean('is_active') && $employee->hasPin() && ! $employee->pin_fingerprint && ! $request->pinWasProvided() && ! $request->shouldRemovePin()) {
            return back()->withErrors(['pin' => 'Asigna un PIN nuevo antes de reactivar este empleado.']);
        }
        $data = $request->validated();
        $roles = $data['roles'];
        unset($data['roles'], $data['pin'], $data['remove_pin']);
        if ($request->shouldRemovePin()) {
            $data['pin_hash'] = null;
            $data['pin_fingerprint'] = null;
        } elseif ($request->pinWasProvided()) {
            $fingerprint = AttendanceService::fingerprint($restaurant->id, $request->input('pin'));
            abort_if($restaurant->employees()->where('is_active', true)->where('id', '<>', $employee->id)->where('pin_fingerprint', $fingerprint)->exists(), 422, 'Ese PIN ya está asignado a otro empleado activo.');
            $data['pin_hash'] = Hash::make($request->input('pin'));
            $data['pin_fingerprint'] = $request->boolean('is_active') ? $fingerprint : null;
        } elseif (! $request->boolean('is_active')) {
            $data['pin_fingerprint'] = null;
        }
        $employee->update($data);
        $employee->operationalRoles()->sync($roles);

        return redirect()->route('restaurant.staff.employees.index', $restaurant)->with('status', 'Empleado actualizado.');
    }
}
