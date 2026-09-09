<?php

namespace App\Http\Controllers;

use App\FinancialService;
use App\Http\Requests\Pos\CloseCashSessionRequest;
use App\Http\Requests\Pos\OpenCashSessionRequest;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class CashController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('usePos', $restaurant);

        return view('pos.cash', ['restaurant' => $restaurant, 'register' => app(FinancialService::class)->register($restaurant), 'session' => $restaurant->cashSessions()->where('status', 'open')->with('register')->first(), 'methods' => app(FinancialService::class)->methods($restaurant)]);
    }

    public function open(OpenCashSessionRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('usePos', $restaurant);
        try {
            $employee = app(FinancialService::class)->verifyOperator($restaurant, $request->integer('employee_id'), $request->string('pin')->toString());
            if (! $employee->operationalRoles->contains('code', 'manager')) {
                throw new InvalidArgumentException('Se requiere un encargado operativo.');
            }
            $register = $restaurant->cashRegisters()->findOrFail($request->integer('register_id'));
            app(FinancialService::class)->openSession($restaurant, $register, $request->integer('opening_float_minor'), $employee, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['cash' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Caja abierta.');
    }

    public function close(CloseCashSessionRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('usePos', $restaurant);
        try {
            $employee = app(FinancialService::class)->verifyOperator($restaurant, $request->integer('manager_employee_id'), $request->string('manager_pin')->toString());
            if (! $employee->operationalRoles->contains('code', 'manager')) {
                throw new InvalidArgumentException('Se requiere un encargado operativo.');
            }
            $session = $restaurant->cashSessions()->findOrFail((int) request('cash_session_id'));
            app(FinancialService::class)->closeSession($session, $request->integer('declared_cash_minor'), $request->input('denominations', []), $employee, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['cash' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Caja cerrada.');
    }
}
