<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pos\AddOrderLineRequest;
use App\Http\Requests\Pos\ChangeOrderLineRequest;
use App\Http\Requests\Pos\OpenOrderRequest;
use App\Http\Requests\Pos\SwitchPosEmployeeRequest;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderRound;
use App\Models\Restaurant;
use App\PosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class PosController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('usePos', $restaurant);
        $tables = $restaurant->diningTables()->with(['zone', 'activeAssignment.order.currentEmployee'])->where('is_active', true)->whereHas('zone', fn ($query) => $query->where('is_active', true))->get()->groupBy('zone_id');
        $zones = $restaurant->zones()->where('is_active', true)->withCount('diningTables')->get();

        return view('pos.index', compact('restaurant', 'tables', 'zones'));
    }

    public function openForm(Restaurant $restaurant, DiningTable $diningTable): View
    {
        $this->authorize('usePos', $restaurant);
        abort_unless($diningTable->restaurant_id === $restaurant->id, 404);
        $employees = $this->operators($restaurant);

        return view('pos.open', compact('restaurant', 'diningTable', 'employees'));
    }

    public function open(OpenOrderRequest $request, Restaurant $restaurant, DiningTable $diningTable): RedirectResponse
    {
        abort_unless($diningTable->restaurant_id === $restaurant->id, 404);
        try {
            $employee = app(PosService::class)->verifyOperator($restaurant, $request->integer('employee_id'), $request->string('pin')->toString());
            $order = app(PosService::class)->open($restaurant, $diningTable, $employee, $request->integer('guest_count') ?: null, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['pin' => $exception->getMessage()])->withInput();
        }
        $this->rememberOperator($restaurant, $order, $employee);

        return redirect()->route('restaurant.pos.orders.show', [$restaurant, $order]);
    }

    public function show(Restaurant $restaurant, Order $order): View
    {
        $this->authorize('view', $order);
        abort_unless($order->restaurant_id === $restaurant->id && $order->status === 'open', 404);
        $order->load(['table.zone', 'currentEmployee', 'rounds.lines.modifiers', 'draftRound.lines.modifiers']);
        $categoryId = request('category');
        $search = trim((string) request('q'));
        $categories = $restaurant->categories()->where('is_active', true)->where('available_dine_in', true)->orderBy('position')->orderBy('id')->get();
        $products = $restaurant->products()->with(['category', 'formats', 'modifierGroupAssignments.group.options'])->where('is_active', true)->where('is_available', true)->where('available_dine_in', true)->whereHas('category', fn ($query) => $query->where('is_active', true)->where('available_dine_in', true))->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$search.'%')->orWhere('short_name', 'like', '%'.$search.'%')))->orderBy('position')->orderBy('id')->get();
        $round = app(PosService::class)->draft($order, request()->user());
        $operatorId = session($this->operatorKey($restaurant, $order));
        $operator = $operatorId ? $restaurant->employees()->with('operationalRoles')->find($operatorId) : null;

        return view('pos.order', ['restaurant' => $restaurant, 'order' => $order, 'categories' => $categories, 'products' => $products, 'round' => $round, 'employees' => $this->operators($restaurant), 'operator' => $operator]);
    }

    public function switchEmployee(SwitchPosEmployeeRequest $request, Restaurant $restaurant, Order $order): RedirectResponse
    {
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        try {
            $employee = app(PosService::class)->verifyOperator($restaurant, $request->integer('employee_id'), $request->string('pin')->toString());
            app(PosService::class)->switchEmployee($order, $employee, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['pin' => $exception->getMessage()])->withInput();
        }
        $this->rememberOperator($restaurant, $order, $employee);

        return back()->with('status', 'Camarero operativo cambiado.');
    }

    public function configurator(Restaurant $restaurant, Order $order, int $product): View
    {
        $this->authorize('view', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        $productModel = $restaurant->products()->with(['category', 'formats', 'modifierGroupAssignments.group.options'])->findOrFail($product);

        return view('pos.configurator', ['restaurant' => $restaurant, 'order' => $order, 'round' => app(PosService::class)->draft($order, request()->user()), 'product' => $productModel]);
    }

    public function add(AddOrderLineRequest $request, Restaurant $restaurant, Order $order, OrderRound $round): RedirectResponse
    {
        $employee = $this->operator($restaurant, $order);
        if (! $employee) {
            return back()->withErrors(['operator' => 'Identifica primero el camarero operativo.']);
        }
        try {
            app(PosService::class)->addLine($order, $round, $request->validated(), $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['product_id' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Producto añadido.');
    }

    public function change(ChangeOrderLineRequest $request, Restaurant $restaurant, Order $order, OrderRound $round, OrderLine $line): RedirectResponse
    {
        $this->operator($restaurant, $order) ?: abort(422, 'Identifica primero el camarero operativo.');
        app(PosService::class)->changeLine($order, $round, $line, $request->integer('quantity'), $request->user());

        return back();
    }

    public function submit(Restaurant $restaurant, Order $order, OrderRound $round): RedirectResponse
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        $this->operator($restaurant, $order) ?: abort(422, 'Identifica primero el camarero operativo.');
        try {
            app(PosService::class)->submit($order, $round, request()->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['round' => $exception->getMessage()]);
        }

        return redirect()->route('restaurant.pos.orders.show', [$restaurant, $order])->with('status', 'Comanda confirmada.');
    }

    public function cancel(Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        try {
            app(PosService::class)->cancelEmpty($order, request()->user());
        } catch (\Throwable $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return redirect()->route('restaurant.pos', $restaurant)->with('status', 'Cuenta vacía cancelada.');
    }

    private function operators(Restaurant $restaurant)
    {
        return $restaurant->employees()->with('operationalRoles')->where('is_active', true)->whereNotNull('pin_hash')->whereHas('operationalRoles', fn ($query) => $query->whereIn('code', ['service', 'manager']))->orderBy('display_name')->get();
    }

    private function operator(Restaurant $restaurant, Order $order): ?Employee
    {
        $id = session($this->operatorKey($restaurant, $order));

        return $id ? $restaurant->employees()->whereKey($id)->where('is_active', true)->whereHas('operationalRoles', fn ($query) => $query->whereIn('code', ['service', 'manager']))->first() : null;
    }

    public function forgetEmployee(Restaurant $restaurant, Order $order): RedirectResponse
    {
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        session()->forget($this->operatorKey($restaurant, $order));

        return back()->with('status', 'TPV bloqueado. Identifica al camarero para continuar.');
    }

    private function rememberOperator(Restaurant $restaurant, Order $order, Employee $employee): void
    {
        session([$this->operatorKey($restaurant, $order) => $employee->id]);
    }

    private function operatorKey(Restaurant $restaurant, Order $order): string
    {
        return 'pos_employee_'.$restaurant->id.'_'.$order->id;
    }
}
