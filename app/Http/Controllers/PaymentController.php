<?php

namespace App\Http\Controllers;

use App\FinancialService;
use App\Http\Requests\Pos\PaymentRequest;
use App\Models\Order;
use App\Models\OrderSplitPart;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function create(Restaurant $restaurant, Order $order): View
    {
        $this->authorize('view', $order);
        abort_unless($order->restaurant_id === $restaurant->id && $order->status === 'open', 404);
        $plan = $order->splitPlans()->where('status', 'active')->with('parts')->first();
        $session = $restaurant->cashSessions()->where('status', 'open')->first();

        return view('pos.payment', ['restaurant' => $restaurant, 'order' => $order->load('table.zone'), 'methods' => app(FinancialService::class)->methods($restaurant), 'session' => $session, 'plan' => $plan]);
    }

    public function store(PaymentRequest $request, Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        $employee = $this->operator($restaurant, $order);
        $session = $restaurant->cashSessions()->findOrFail($request->integer('cash_session_id'));
        try {
            $payment = app(FinancialService::class)->pay($order, null, $request->validated('tenders'), $request->string('request_key')->toString(), $employee, $request->user(), $session);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('restaurant.pos.orders.show', [$restaurant, $order])->with('status', 'Pago registrado: '.number_format($payment->amount_minor / 100, 2, ',', '.').' €');
    }

    public function part(Restaurant $restaurant, OrderSplitPart $part): View
    {
        $order = $part->plan->order;
        $this->authorize('view', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);

        return view('pos.payment', ['restaurant' => $restaurant, 'order' => $order->load('table.zone'), 'part' => $part, 'methods' => app(FinancialService::class)->methods($restaurant), 'session' => $restaurant->cashSessions()->where('status', 'open')->first(), 'plan' => $part->plan]);
    }

    public function storePart(PaymentRequest $request, Restaurant $restaurant, OrderSplitPart $part): RedirectResponse
    {
        $order = $part->plan->order;
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        $employee = $this->operator($restaurant, $order);
        $session = $restaurant->cashSessions()->findOrFail($request->integer('cash_session_id'));
        try {
            app(FinancialService::class)->pay($order, $part, $request->validated('tenders'), $request->string('request_key')->toString(), $employee, $request->user(), $session);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('restaurant.pos.orders.split', [$restaurant, $order])->with('status', 'Pago de subcuenta registrado.');
    }

    private function operator(Restaurant $restaurant, Order $order)
    {
        $id = session('pos_employee_'.$restaurant->id.'_'.$order->id);
        $employee = $restaurant->employees()->with('operationalRoles')->whereKey($id)->where('is_active', true)->first();
        if (! $employee || ! $employee->operationalRoles->contains(fn ($role) => in_array($role->code, ['service', 'manager'], true))) {
            throw new InvalidArgumentException('Identifica primero el camarero operativo.');
        }

        return $employee;
    }
}
