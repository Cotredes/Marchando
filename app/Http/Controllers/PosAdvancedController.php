<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderSplitPlan;
use App\Models\Restaurant;
use App\PosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class PosAdvancedController extends Controller
{
    public function transferForm(Restaurant $restaurant, Order $order): View
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        $tables = $restaurant->diningTables()->with('zone')->where('is_active', true)->whereHas('zone', fn ($q) => $q->where('is_active', true))->where('id', '<>', $order->dining_table_id)->whereDoesntHave('activeAssignment')->get();

        return view('pos.transfer', compact('restaurant', 'order', 'tables'));
    }

    public function transfer(Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        try {
            $employee = $this->operator($restaurant, $order);
            $destination = $restaurant->diningTables()->with('zone')->findOrFail(request()->integer('dining_table_id'));
            app(PosService::class)->transfer($order, $destination, $employee, request()->user());
        } catch (\Throwable $exception) {
            return back()->withErrors(['dining_table_id' => $exception->getMessage()]);
        }

        return redirect()->route('restaurant.pos.orders.show', [$restaurant, $order])->with('status', 'Cuenta trasladada.');
    }

    public function void(Restaurant $restaurant, Order $order, OrderLine $line): RedirectResponse
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id && $line->order_id === $order->id, 404);
        try {
            $manager = $this->manager($restaurant);
            app(PosService::class)->voidLine($line, max(1, (int) request('quantity')), (string) request('reason', 'Anulación operativa'), $manager, request()->user());
        } catch (\Throwable $exception) {
            return back()->withErrors(['void' => $exception->getMessage()]);
        }

        return back()->with('status', 'Línea anulada y auditada.');
    }

    public function discount(Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        try {
            $manager = $this->manager($restaurant);
            app(PosService::class)->discount($order, (string) request('kind'), (int) request('value'), (string) request('reason', 'Descuento manual'), $manager, request()->user());
        } catch (\Throwable $exception) {
            return back()->withErrors(['discount' => $exception->getMessage()]);
        }

        return back()->with('status', 'Descuento aplicado y auditado.');
    }

    public function manualPrice(Restaurant $restaurant, Order $order, OrderLine $line): RedirectResponse
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id && $line->order_id === $order->id, 404);
        try {
            app(PosService::class)->setManualPrice($line, (int) request('price_minor'), (string) request('reason', 'Precio acordado'), $this->manager($restaurant), request()->user());
        } catch (\Throwable $exception) {
            return back()->withErrors(['price' => $exception->getMessage()]);
        }

        return back()->with('status', 'Precio manual aplicado y auditado.');
    }

    public function splitForm(Restaurant $restaurant, Order $order): View
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);

        return view('pos.split', ['restaurant' => $restaurant, 'order' => $order->load('lines.modifiers'), 'activePlan' => $order->splitPlans()->where('status', 'active')->with('parts.allocations.line')->first()]);
    }

    public function splitEqual(Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('operate', $order);
        try {
            $manager = $this->operator($restaurant, $order);
            app(PosService::class)->splitEqual($order, (int) request('parts'), $manager, request()->user());
        } catch (\Throwable $exception) {
            return back()->withErrors(['split' => $exception->getMessage()]);
        }

        return back()->with('status', 'Split preparado.');
    }

    public function splitProducts(Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('operate', $order);
        try {
            $employee = $this->operator($restaurant, $order);
            app(PosService::class)->splitProducts($order, request()->input('allocations', []), $employee, request()->user());
        } catch (\Throwable $exception) {
            return back()->withErrors(['split' => $exception->getMessage()]);
        }

        return back()->with('status', 'Split por productos preparado.');
    }

    public function cancelSplit(Restaurant $restaurant, OrderSplitPlan $plan): RedirectResponse
    {
        abort_unless($plan->restaurant_id === $restaurant->id, 404);
        $order = $plan->order;
        $this->authorize('operate', $order);
        try {
            app(PosService::class)->cancelSplit($plan, request()->user(), $this->operator($restaurant, $order));
        } catch (\Throwable $exception) {
            return back()->withErrors(['split' => $exception->getMessage()]);
        }

        return back()->with('status', 'Split cancelado; la cuenta original no ha cambiado.');
    }

    public function history(Restaurant $restaurant, Order $order): View
    {
        $this->authorize('view', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);

        return view('pos.history', ['restaurant' => $restaurant, 'order' => $order->load(['events.employee', 'table.zone'])]);
    }

    public function recovery(Restaurant $restaurant): View
    {
        $this->authorize('viewSensitiveOrders', $restaurant);

        return view('pos.recovery', ['restaurant' => $restaurant, 'orders' => $restaurant->orders()->where('status', 'cancelled')->with('table')->latest('closed_at')->paginate(25), 'tables' => $restaurant->diningTables()->with('zone')->where('is_active', true)->whereHas('zone', fn ($q) => $q->where('is_active', true))->whereDoesntHave('activeAssignment')->get()]);
    }

    public function recover(Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('viewSensitiveOrders', $restaurant);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        try {
            $employee = $this->operator($restaurant, $order);
            $destination = $restaurant->diningTables()->findOrFail(request()->integer('dining_table_id'));
            app(PosService::class)->recover($order, $destination, $employee, request()->user());
        } catch (\Throwable $exception) {
            return back()->withErrors(['recovery' => $exception->getMessage()]);
        }

        return redirect()->route('restaurant.pos.orders.show', [$restaurant, $order])->with('status', 'Cuenta recuperada.');
    }

    private function operator(Restaurant $restaurant, Order $order): Employee
    {
        $id = session('pos_employee_'.$restaurant->id.'_'.$order->id);
        $employee = $restaurant->employees()->with('operationalRoles')->whereKey($id)->where('is_active', true)->first();
        if (! $employee) {
            throw new InvalidArgumentException('Identifica primero el camarero operativo.');
        }

        return $employee;
    }

    private function manager(Restaurant $restaurant): Employee
    {
        $employee = $this->operator($restaurant, Order::findOrFail(request()->route('order')->id));
        if (! $employee->operationalRoles->contains('code', 'manager')) {
            throw new InvalidArgumentException('Se requiere un encargado operativo.');
        }

        return $employee;
    }
}
