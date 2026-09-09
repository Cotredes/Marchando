<?php

namespace App;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Employee;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CouponService
{
    public function normalizeCode(?string $code): string
    {
        return mb_strtoupper(trim((string) $code));
    }

    public function customerFp(?int $customerId, ?string $phone): ?string
    {
        if ($customerId) {
            return 'customer-'.$customerId;
        }
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (! $digits || strlen($digits) < 6) {
            return null;
        }

        return 'phone-'.hash('sha256', $digits);
    }

    public function validate($restaurant, string $code, string $channel, int $subtotalMinor, ?string $customerFp = null): Coupon
    {
        $coupon = $restaurant->coupons()->where('code', $this->normalizeCode($code))->first();
        if (! $coupon || ! $coupon->is_active) {
            throw new InvalidArgumentException('El código promocional no es válido.');
        }
        $now = now();
        if (($coupon->starts_at && $coupon->starts_at->greaterThan($now)) || ($coupon->ends_at && $coupon->ends_at->lessThan($now))) {
            throw new InvalidArgumentException('El código promocional está caducado o aún no es válido.');
        }
        if ($coupon->channels && ! in_array($channel, $coupon->channels, true)) {
            throw new InvalidArgumentException('El código promocional no es válido para este canal.');
        }
        if ($subtotalMinor < $coupon->min_order_minor) {
            throw new InvalidArgumentException('Te faltan '.CatalogMoney::format($coupon->min_order_minor - $subtotalMinor).' € para usar este cupón.');
        }
        if ($coupon->max_uses !== null && $coupon->uses_count >= $coupon->max_uses) {
            throw new InvalidArgumentException('Este código promocional ya se ha agotado.');
        }
        if ($coupon->one_per_customer && $customerFp && $coupon->redemptions()->where('customer_fp', $customerFp)->exists()) {
            throw new InvalidArgumentException('Este código promocional ya se usó en esta cuenta.');
        }

        return $coupon;
    }

    public function amountFor(Coupon $coupon, int $subtotalMinor): int
    {
        if ($coupon->kind === 'percent') {
            return min($subtotalMinor, intdiv($subtotalMinor * $coupon->value + 5000, 10000));
        }

        return min($subtotalMinor, $coupon->value);
    }

    public function applyToOrder(Order $incoming, string $code, ?string $customerFp, ?User $user, ?Employee $employee = null): Order
    {
        return DB::transaction(function () use ($incoming, $code, $customerFp, $user, $employee): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($incoming->id);
            abort_unless($order->status === 'open', 422, 'La cuenta ya no admite descuentos.');
            if ($order->payments()->where('status', 'succeeded')->exists()) {
                throw new InvalidArgumentException('La cuenta ya ha iniciado el cobro.');
            }
            if ($order->discounts()->where('is_active', true)->exists()) {
                throw new InvalidArgumentException('Esta cuenta ya tiene un descuento: no se pueden acumular promociones.');
            }
            $subtotal = (int) $order->lines()->sum('active_line_total_minor');
            $coupon = Coupon::query()->lockForUpdate()->where('restaurant_id', $order->restaurant_id)->where('code', $this->normalizeCode($code))->first();
            if (! $coupon) {
                throw new InvalidArgumentException('El código promocional no es válido.');
            }
            $this->validate($order->restaurant, $coupon->code, $order->channel, $subtotal, $customerFp);
            $amount = $this->amountFor($coupon, $subtotal);
            if ($amount < 1) {
                throw new InvalidArgumentException('El cupón no genera descuento sobre este importe.');
            }
            $kind = $coupon->kind === 'percent' ? 'percentage' : 'fixed';
            $order->discounts()->create([
                'restaurant_id' => $order->restaurant_id, 'user_id' => $user?->id, 'employee_id' => $employee?->id,
                'coupon_id' => $coupon->id, 'source' => 'coupon', 'kind' => $kind,
                'percentage_basis_points' => $kind === 'percentage' ? $coupon->value : null,
                'fixed_minor' => $kind === 'fixed' ? $coupon->value : null,
                'subtotal_minor' => $subtotal, 'discount_minor' => $amount, 'total_minor' => max(0, $subtotal - $amount),
                'reason' => 'Cupón '.$coupon->code, 'is_active' => true,
            ]);
            $order->update(['total_minor' => max(0, $subtotal - $amount) + (int) $order->charges()->sum('amount_minor'), 'version' => $order->version + 1]);
            $coupon->increment('uses_count');
            CouponRedemption::create(['coupon_id' => $coupon->id, 'restaurant_id' => $order->restaurant_id, 'order_id' => $order->id, 'customer_id' => $order->customer_id, 'customer_fp' => $customerFp, 'amount_minor' => $amount]);
            $order->events()->create(['restaurant_id' => $order->restaurant_id, 'user_id' => $user?->id, 'employee_id' => $employee?->id, 'type' => 'coupon_applied', 'data' => ['coupon_id' => $coupon->id, 'code' => $coupon->code, 'discount_minor' => $amount]]);

            return $order->fresh();
        });
    }
}
