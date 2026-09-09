<?php

namespace App\Http\Controllers;

use App\CouponService;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Restaurant;
use App\PosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class CouponController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('manageIntegrations', $restaurant);

        return view('integrations.coupons', ['restaurant' => $restaurant, 'coupons' => $restaurant->coupons()->withCount('redemptions')->get()]);
    }

    public function store(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $data = $this->validated();
        $data['code'] = app(CouponService::class)->normalizeCode($data['code']);
        if ($restaurant->coupons()->where('code', $data['code'])->exists()) {
            return back()->withErrors(['code' => 'Ya existe un cupón con ese código.'])->withInput();
        }
        $restaurant->coupons()->create([...$data, 'is_active' => true, 'uses_count' => 0]);

        return back()->with('status', 'Cupón creado.');
    }

    public function update(Restaurant $restaurant, Coupon $coupon): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        abort_unless($coupon->restaurant_id === $restaurant->id, 404);
        $data = $this->validated();
        $data['code'] = app(CouponService::class)->normalizeCode($data['code']);
        if ($restaurant->coupons()->where('code', $data['code'])->whereKeyNot($coupon->id)->exists()) {
            return back()->withErrors(['code' => 'Ya existe un cupón con ese código.'])->withInput();
        }
        $coupon->update([...$data, 'is_active' => request()->boolean('is_active')]);

        return back()->with('status', 'Cupón actualizado.');
    }

    public function applyToOrder(Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('operate', $order);
        abort_unless($order->restaurant_id === $restaurant->id, 404);
        try {
            $employee = app(PosService::class)->verifyOperator($restaurant, (int) request('employee_id'), (string) request('pin'));
            $fp = app(CouponService::class)->customerFp($order->customer_id, $order->customer?->phone);
            app(CouponService::class)->applyToOrder($order, (string) request('code'), $fp, request()->user(), $employee);
        } catch (\Throwable $exception) {
            return back()->withErrors(['coupon' => $exception->getMessage()]);
        }

        return back()->with('status', 'Cupón aplicado.');
    }

    private function validated(): array
    {
        $data = request()->validate([
            'code' => ['required', 'string', 'max:40'], 'name' => ['nullable', 'string', 'max:120'],
            'kind' => ['required', 'in:percent,fixed'], 'value' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'],
            'channels' => ['nullable', 'array'], 'channels.*' => ['in:dine_in,takeaway,delivery'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'], 'max_uses' => ['nullable', 'integer', 'min:1'],
            'one_per_customer' => ['sometimes', 'boolean'],
        ]);
        if (($data['kind'] ?? '') === 'percent' && (int) $data['value'] > 10000) {
            throw new InvalidArgumentException('El porcentaje no puede superar el 100 %.');
        }

        return [...$data, 'min_order_minor' => $data['min_order_minor'] ?? 0, 'one_per_customer' => request()->boolean('one_per_customer')];
    }
}
