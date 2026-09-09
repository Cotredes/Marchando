<?php

namespace App\Http\Controllers;

use App\AttendanceService;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Http\Requests\ManualAttendanceRequest;
use App\Models\Restaurant;
use App\Models\WorkInterval;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('viewStaff', $restaurant);
        $date = request('date', now($restaurant->timezone)->toDateString());
        $localStart = CarbonImmutable::parse($date, $restaurant->timezone)->startOfDay();
        $localEnd = $localStart->addDay();
        $query = $restaurant->workIntervals()->with('employee.operationalRoles')->where('started_at', '<', $localEnd->utc())->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $localStart->utc()));
        if (request('employee')) {
            $query->where('employee_id', request('employee'));
        }
        $intervals = $query->orderBy('started_at')->get();

        return view('staff.index', ['restaurant' => $restaurant, 'employees' => $restaurant->employees()->with('operationalRoles')->orderBy('display_name')->get(), 'intervals' => $intervals, 'date' => $date, 'canManage' => auth()->user()->can('manageAttendance', $restaurant)]);
    }

    public function edit(Restaurant $restaurant, WorkInterval $interval): View
    {
        $this->authorize('manageAttendance', $restaurant);
        abort_unless($interval->restaurant_id === $restaurant->id, 404);

        return view('staff.interval-form', compact('restaurant', 'interval'));
    }

    public function add(ManualAttendanceRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $employee = $restaurant->employees()->findOrFail($request->integer('employee_id'));
        $started = CarbonImmutable::parse($request->date('started_at'), $restaurant->timezone)->utc();
        $ended = $request->filled('ended_at') ? CarbonImmutable::parse($request->date('ended_at'), $restaurant->timezone)->utc() : null;
        try {
            app(AttendanceService::class)->addManual($employee, $started, $ended, $request->string('reason')->toString(), auth()->id(), auth()->user()->name);
        } catch (\Throwable $e) {
            return back()->withErrors(['started_at' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Intervalo añadido y auditado.');
    }

    public function correct(AttendanceCorrectionRequest $request, Restaurant $restaurant, WorkInterval $interval): RedirectResponse
    {
        $started = CarbonImmutable::parse($request->date('started_at'), $restaurant->timezone)->utc();
        $ended = $request->filled('ended_at') ? CarbonImmutable::parse($request->date('ended_at'), $restaurant->timezone)->utc() : null;
        try {
            app(AttendanceService::class)->correct($interval, $started, $ended, $request->string('reason')->toString(), auth()->id(), auth()->user()->name);
        } catch (\Throwable $e) {
            return back()->withErrors(['started_at' => $e->getMessage()])->withInput();
        }

        return redirect()->route('restaurant.staff', [$restaurant, 'date' => $started->setTimezone($restaurant->timezone)->toDateString()])->with('status', 'Corrección guardada y auditada.');
    }
}
