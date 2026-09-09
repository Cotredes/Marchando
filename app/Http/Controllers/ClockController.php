<?php

namespace App\Http\Controllers;

use App\AttendanceService;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClockController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('viewStaff', $restaurant);
        $employeeId = session('clock_employee_'.$restaurant->id);
        $selected = $employeeId ? $restaurant->employees()->whereKey($employeeId)->with('workIntervals')->first() : null;

        return view('staff.clock', ['restaurant' => $restaurant, 'employees' => $restaurant->employees()->where('is_active', true)->whereNotNull('pin_hash')->orderBy('display_name')->get(), 'selected' => $selected]);
    }

    public function identify(Restaurant $restaurant): RedirectResponse
    {
        $employee = $restaurant->employees()->whereKey(request('employee_id'))->where('is_active', true)->first();
        $pin = (string) request('pin');
        if (! $employee || ! $employee->hasPin() || ! preg_match('/^\d{4,8}$/', $pin) || ! Hash::check($pin, $employee->pin_hash)) {
            return back()->withErrors(['pin' => 'No se pudo verificar el PIN.'])->withInput(['employee_id' => request('employee_id')]);
        }
        session(['clock_employee_'.$restaurant->id => $employee->id]);

        return redirect()->route('restaurant.staff.clock', $restaurant);
    }

    public function punch(Restaurant $restaurant): RedirectResponse
    {
        $employee = $restaurant->employees()->whereKey(session('clock_employee_'.$restaurant->id))->where('is_active', true)->firstOrFail();
        $interval = app(AttendanceService::class)->punch($employee);
        $message = $interval->ended_at ? 'Salida registrada a '.$interval->ended_at->setTimezone($restaurant->timezone)->format('H:i') : 'Entrada registrada a '.$interval->started_at->setTimezone($restaurant->timezone)->format('H:i');
        session()->forget('clock_employee_'.$restaurant->id);

        return redirect()->route('restaurant.staff.clock', $restaurant)->with('clock_status', $message);
    }

    public function forget(Restaurant $restaurant): RedirectResponse
    {
        session()->forget('clock_employee_'.$restaurant->id);

        return back();
    }
}
