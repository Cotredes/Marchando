<?php

namespace App;

use App\Models\ActiveTableOrder;
use App\Models\CashCount;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderSplitPart;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReversal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class FinancialService
{
    public function verifyOperator($restaurant, int $employeeId, string $pin): Employee
    {
        $employee = $restaurant->employees()->with('operationalRoles')->whereKey($employeeId)->where('is_active', true)->first();
        if (! $employee || ! $employee->hasPin() || ! Hash::check($pin, $employee->pin_hash)) {
            throw new InvalidArgumentException('No se pudo verificar el empleado operativo.');
        }

        return $employee;
    }

    public function methods($restaurant)
    {
        $methods = $restaurant->paymentMethods()->get();
        if ($methods->isNotEmpty()) {
            return $methods;
        }

        $restaurant->paymentMethods()->createMany([
            ['code' => 'cash', 'name' => 'Efectivo', 'is_cash' => true, 'position' => 1],
            ['code' => 'card', 'name' => 'Tarjeta', 'is_cash' => false, 'position' => 2],
        ]);

        return $restaurant->paymentMethods()->get();
    }

    public function register($restaurant): CashRegister
    {
        return $restaurant->cashRegisters()->firstOrCreate(['name' => 'Caja principal'], ['is_active' => true]);
    }

    public function openSession($restaurant, CashRegister $register, int $openingFloat, Employee $employee, User $user): CashSession
    {
        return DB::transaction(function () use ($restaurant, $register, $openingFloat, $employee, $user) {
            $register = CashRegister::query()->lockForUpdate()->findOrFail($register->id);
            abort_unless($register->restaurant_id === $restaurant->id && $register->is_active, 404);
            if ($openingFloat < 0 || $restaurant->employees()->whereKey($employee->id)->doesntExist()) {
                throw new InvalidArgumentException('Los datos de apertura no son válidos.');
            }
            if (CashSession::query()->where('restaurant_id', $restaurant->id)->where('cash_register_id', $register->id)->where('status', 'open')->lockForUpdate()->exists()) {
                throw new InvalidArgumentException('La caja ya tiene una sesión abierta.');
            }

            return CashSession::create(['restaurant_id' => $restaurant->id, 'cash_register_id' => $register->id, 'opened_by_user_id' => $user->id, 'opened_by_employee_id' => $employee->id, 'status' => 'open', 'open_slot' => 1, 'opening_float_minor' => $openingFloat, 'opened_at' => now()]);
        });
    }

    public function pay(Order $incomingOrder, ?OrderSplitPart $incomingPart, array $tenders, string $requestKey, Employee $employee, User $user, CashSession $incomingSession): Payment
    {
        return DB::transaction(function () use ($incomingOrder, $incomingPart, $tenders, $requestKey, $employee, $user, $incomingSession) {
            $existing = Payment::query()->where('restaurant_id', $incomingOrder->restaurant_id)->where('request_key', $requestKey)->with('tenders')->first();
            if ($existing) {
                return $existing;
            }

            $order = Order::query()->lockForUpdate()->findOrFail($incomingOrder->id);
            abort_unless($order->restaurant_id === $incomingSession->restaurant_id && $order->restaurant_id === $employee->restaurant_id, 404);
            if ($order->status !== 'open' || $order->payment_status === 'paid') {
                throw new InvalidArgumentException('La cuenta ya no admite cobros.');
            }
            if ($order->draftRound()->whereHas('lines')->exists()) {
                throw new InvalidArgumentException('Confirma la comanda antes de cobrar.');
            }

            $part = null;
            $plan = $order->splitPlans()->where('status', 'active')->with('parts')->lockForUpdate()->first();
            if ($incomingPart) {
                $part = OrderSplitPart::query()->lockForUpdate()->findOrFail($incomingPart->id);
                $partPlan = $part->plan()->lockForUpdate()->firstOrFail();
                abort_unless($partPlan->order_id === $order->id && $partPlan->restaurant_id === $order->restaurant_id && $partPlan->status === 'active', 404);
                if ($partPlan->source_total_minor !== $order->total_minor) {
                    throw new InvalidArgumentException('El split ha quedado desactualizado; vuelve a prepararlo.');
                }
            } elseif ($plan) {
                throw new InvalidArgumentException('Esta cuenta tiene un split activo. Cobra sus subcuentas.');
            }

            $targetPaid = (int) Payment::query()->where('order_id', $order->id)->where('status', 'succeeded')->when($part, fn ($q) => $q->where('order_split_part_id', $part->id))->sum('amount_minor');
            $targetReversed = (int) PaymentReversal::query()->whereHas('payment', fn ($q) => $q->where('order_id', $order->id)->when($part, fn ($q) => $q->where('order_split_part_id', $part->id)))->sum('amount_minor');
            $targetTotal = $part ? $part->total_minor : $order->total_minor;
            $remaining = $targetTotal - $targetPaid + $targetReversed;
            if ($remaining <= 0) {
                throw new InvalidArgumentException('Este importe ya está pagado.');
            }

            $session = CashSession::query()->lockForUpdate()->findOrFail($incomingSession->id);
            abort_unless($session->restaurant_id === $order->restaurant_id && $session->status === 'open', 422);
            $methodIds = collect($tenders)->pluck('method_id')->map(fn ($id) => (int) $id)->unique();
            $methods = PaymentMethod::query()->where('restaurant_id', $order->restaurant_id)->whereIn('id', $methodIds)->where('is_active', true)->get()->keyBy('id');
            $amount = 0;
            foreach ($tenders as $tender) {
                $method = $methods->get((int) ($tender['method_id'] ?? 0));
                $tenderAmount = (int) ($tender['amount_minor'] ?? 0);
                $cashReceived = $method?->is_cash && array_key_exists('tendered_minor', $tender) ? (int) $tender['tendered_minor'] : $tenderAmount;
                if (! $method || $tenderAmount < 1 || $cashReceived < $tenderAmount) {
                    throw new InvalidArgumentException('El desglose del pago no es válido.');
                }
                $amount += $tenderAmount;
            }
            if ($amount < 1 || $amount > $remaining) {
                throw new InvalidArgumentException('El pago no puede superar el importe pendiente.');
            }

            $payment = Payment::create(['restaurant_id' => $order->restaurant_id, 'order_id' => $order->id, 'order_split_plan_id' => $part?->order_split_plan_id, 'order_split_part_id' => $part?->id, 'cash_session_id' => $session->id, 'user_id' => $user->id, 'employee_id' => $employee->id, 'status' => 'succeeded', 'currency' => $order->currency, 'amount_minor' => $amount, 'request_key' => $requestKey, 'business_date' => $order->business_date, 'succeeded_at' => now()]);
            foreach ($tenders as $tender) {
                $method = $methods->get((int) $tender['method_id']);
                $tenderAmount = (int) $tender['amount_minor'];
                $received = $method->is_cash && array_key_exists('tendered_minor', $tender) ? (int) $tender['tendered_minor'] : $tenderAmount;
                $payment->tenders()->create(['payment_method_id' => $method->id, 'amount_minor' => $tenderAmount, 'tendered_minor' => $method->is_cash ? $received : null, 'change_minor' => $method->is_cash ? $received - $tenderAmount : 0]);
                if ($method->is_cash) {
                    CashMovement::create(['restaurant_id' => $order->restaurant_id, 'cash_session_id' => $session->id, 'payment_id' => $payment->id, 'user_id' => $user->id, 'employee_id' => $employee->id, 'type' => 'sale', 'amount_minor' => $tenderAmount]);
                }
            }

            if ($part) {
                $partPaid = (int) Payment::query()->where('order_split_part_id', $part->id)->where('status', 'succeeded')->sum('amount_minor');
                $part->update(['status' => $partPaid >= $part->total_minor ? 'paid' : 'partial', 'version' => $part->version + 1]);
            }

            $this->refreshOrderStatus($order->fresh(), $user, $employee);
            $settled = $order->fresh();
            app(CashDrawerService::class)->openForCashPayment($payment, $settled->restaurant);
            if ($settled->status === 'paid') {
                app(PrintService::class)->enqueueCustomerTicket($settled, null, $settled->restaurant, $user, $employee);
            }
            $fired = $payment->fresh('tenders.method');
            DB::afterCommit(fn () => app(OutboundWebhookService::class)->fire($settled->restaurant, 'payment.completed', ['order_id' => $settled->id, 'payment_id' => $payment->id, 'amount_minor' => $payment->amount_minor]));

            return $fired;
        });
    }

    public function payOnline(Order $incomingOrder, $intent, ?User $actor): Payment
    {
        return DB::transaction(function () use ($incomingOrder, $intent, $actor): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($incomingOrder->id);
            abort_unless($order->restaurant_id === $intent->restaurant_id && in_array($order->channel, ['takeaway', 'delivery'], true), 404);
            if ($order->status !== 'open' || $order->payment_status === 'paid') {
                throw new InvalidArgumentException('La cuenta ya no admite cobros.');
            }
            if ($order->splitPlans()->where('status', 'active')->exists()) {
                throw new InvalidArgumentException('La cuenta tiene un split activo.');
            }
            $existing = Payment::query()->where('restaurant_id', $order->restaurant_id)->where('request_key', 'online-pay-'.$intent->id)->first();
            if ($existing) {
                return $existing;
            }
            $paid = (int) $order->payments()->where('status', 'succeeded')->sum('amount_minor');
            $reversed = (int) PaymentReversal::query()->whereHas('payment', fn ($q) => $q->where('order_id', $order->id))->sum('amount_minor');
            if ($intent->amount_minor !== $order->total_minor - $paid + $reversed) {
                throw new InvalidArgumentException('El importe pagado online no coincide con el total de la cuenta.');
            }
            $method = app(OnlinePaymentService::class)->ensureOnlineMethod($order->restaurant);
            $payment = Payment::create(['restaurant_id' => $order->restaurant_id, 'order_id' => $order->id, 'cash_session_id' => null, 'user_id' => $actor?->id, 'employee_id' => null, 'status' => 'succeeded', 'currency' => $order->currency, 'amount_minor' => $intent->amount_minor, 'request_key' => 'online-pay-'.$intent->id, 'reference' => $intent->provider_intent_id, 'business_date' => $order->business_date, 'succeeded_at' => now()]);
            $payment->tenders()->create(['payment_method_id' => $method->id, 'amount_minor' => $intent->amount_minor, 'tendered_minor' => null, 'change_minor' => 0]);
            $this->refreshOrderStatus($order->fresh(), $actor, $order->currentEmployee ?? $order->openedByEmployee);

            return $payment->load('tenders.method');
        });
    }

    public function reverse(Payment $incomingPayment, string $reason, string $requestKey, Employee $manager, User $user): PaymentReversal
    {
        return DB::transaction(function () use ($incomingPayment, $reason, $requestKey, $manager, $user) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($incomingPayment->id);
            abort_unless($payment->restaurant_id === $manager->restaurant_id && $payment->status === 'succeeded', 404);
            if ($payment->reversal()->exists()) {
                return $payment->reversal;
            }
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $reversal = PaymentReversal::create(['payment_id' => $payment->id, 'restaurant_id' => $payment->restaurant_id, 'user_id' => $user->id, 'employee_id' => $manager->id, 'amount_minor' => $payment->amount_minor, 'reason' => $reason, 'request_key' => $requestKey]);
            $this->refreshOrderStatus($order, $user, $manager);

            return $reversal;
        });
    }

    public function closeSession(CashSession $incomingSession, int $declared, array $denominations, Employee $manager, User $user): CashSession
    {
        return DB::transaction(function () use ($incomingSession, $declared, $denominations, $manager, $user) {
            $session = CashSession::query()->lockForUpdate()->findOrFail($incomingSession->id);
            if ($session->status !== 'open' || $declared < 0) {
                throw new InvalidArgumentException('La sesión de caja no puede cerrarse.');
            }
            $sales = (int) $session->payments()->where('status', 'succeeded')->whereHas('tenders.method', fn ($q) => $q->where('is_cash', true))->with('tenders')->get()->flatMap->tenders->sum('amount_minor');
            $refunds = (int) $session->movements()->where('type', 'refund')->sum('amount_minor');
            $paidIn = (int) $session->movements()->where('type', 'paid_in')->sum('amount_minor');
            $paidOut = (int) $session->movements()->where('type', 'paid_out')->sum('amount_minor');
            $expected = $session->opening_float_minor + $sales - $refunds + $paidIn - $paidOut;
            CashCount::create(['cash_session_id' => $session->id, 'user_id' => $user->id, 'employee_id' => $manager->id, 'declared_cash_minor' => $declared, 'expected_cash_minor' => $expected, 'difference_minor' => $declared - $expected, 'denominations' => $denominations]);
            $session->update(['status' => 'closed', 'open_slot' => null, 'closed_by_user_id' => $user->id, 'closed_by_employee_id' => $manager->id, 'expected_cash_minor' => $expected, 'declared_cash_minor' => $declared, 'difference_minor' => $declared - $expected, 'closed_at' => now()]);

            return $session->fresh();
        });
    }

    private function refreshOrderStatus(Order $order, User $user, ?Employee $employee): void
    {
        $paid = (int) $order->payments()->where('status', 'succeeded')->sum('amount_minor');
        $reversed = (int) PaymentReversal::query()->whereHas('payment', fn ($q) => $q->where('order_id', $order->id))->sum('amount_minor');
        $net = $paid - $reversed;
        $wasClosed = $order->status === 'paid';
        $status = $net >= $order->total_minor ? 'paid' : ($wasClosed ? 'payment_due' : ($net > 0 ? 'partial' : 'unpaid'));
        $fields = ['payment_status' => $status, 'version' => $order->version + 1];
        if ($status === 'paid') {
            $fields += ['status' => 'paid', 'paid_at' => now(), 'closed_at' => now()];
            ActiveTableOrder::query()->where('order_id', $order->id)->delete();
            $order->splitPlans()->where('status', 'active')->update(['status' => 'completed']);
        }
        $order->update($fields);
        $order->events()->create(['restaurant_id' => $order->restaurant_id, 'user_id' => $user->id, 'employee_id' => $employee?->id, 'type' => $status === 'paid' ? 'paid' : 'payment_updated', 'data' => ['paid_minor' => $net, 'remaining_minor' => max(0, $order->total_minor - $net)]]);
        if ($status === 'paid' && ! $wasClosed) {
            app(LoyaltyService::class)->accrueForOrder($order->fresh());
            app(OutboundWebhookService::class)->fire($order->restaurant, 'order.completed', ['order_id' => $order->id, 'total_minor' => $order->total_minor, 'channel' => $order->channel]);
        }
        if ($wasClosed && $status !== 'paid') {
            app(LoyaltyService::class)->revertForOrder($order->fresh(), $user, $employee);
        }
    }
}
