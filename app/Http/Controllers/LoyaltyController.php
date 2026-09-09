<?php

namespace App\Http\Controllers;

use App\LoyaltyService;
use App\Models\LoyaltyProgram;
use App\Models\Order;
use App\Models\Restaurant;
use App\PosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoyaltyController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('manageIntegrations', $restaurant);

        return view('integrations.loyalty', ['restaurant' => $restaurant, 'programs' => $restaurant->loyaltyPrograms()->with('rewardProduct')->get(), 'products' => $restaurant->products()->where('is_active', true)->orderBy('name')->limit(200)->get(), 'categories' => $restaurant->categories()->orderBy('name')->get()]);
    }

    public function store(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $data = request()->validate(['name' => ['required', 'string', 'max:120'], 'target_type' => ['required', 'in:product,category'], 'target_id' => ['required', 'integer', 'min:1'], 'goal' => ['required', 'integer', 'min:2', 'max:100'], 'reward_product_id' => ['required', 'integer']]);
        $reward = $restaurant->products()->findOrFail($data['reward_product_id']);
        if ($data['target_type'] === 'product') {
            $restaurant->products()->findOrFail($data['target_id']);
        } else {
            $restaurant->categories()->findOrFail($data['target_id']);
        }
        $restaurant->loyaltyPrograms()->create([...$data, 'is_active' => true]);

        return back()->with('status', 'Programa de fidelización creado. Recompensa: '.$reward->name.'.');
    }

    public function update(Restaurant $restaurant, LoyaltyProgram $loyaltyProgram): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        abort_unless($loyaltyProgram->restaurant_id === $restaurant->id, 404);
        $program = $loyaltyProgram;
        $program->update(['is_active' => request()->boolean('is_active')]);

        return back()->with('status', 'Programa actualizado.');
    }

    public function redeem(Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        try {
            $employee = app(PosService::class)->verifyOperator($restaurant, (int) request('employee_id'), (string) request('pin'));
            $program = $restaurant->loyaltyPrograms()->findOrFail((int) request('loyalty_program_id'));
            app(LoyaltyService::class)->redeem($order, $program, request()->user(), $employee);
        } catch (\Throwable $exception) {
            return back()->withErrors(['loyalty' => $exception->getMessage()]);
        }

        return back()->with('status', 'Recompensa canjeada y registrada.');
    }
}
