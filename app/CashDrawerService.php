<?php

namespace App;

use App\Models\CashDrawerEvent;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashDrawerService
{
    public function openManual($restaurant, $session, Employee $employee, User $user): CashDrawerEvent
    {
        return DB::transaction(function () use ($restaurant, $session, $employee, $user): CashDrawerEvent {
            abort_unless($session->restaurant_id === $restaurant->id && $employee->restaurant_id === $restaurant->id, 404);
            $event = CashDrawerEvent::create(['restaurant_id' => $restaurant->id, 'cash_session_id' => $session->id, 'user_id' => $user->id, 'employee_id' => $employee->id, 'trigger' => 'manual', 'status' => 'requested']);
            $this->dispatchKick($restaurant, $event);

            return $event->fresh();
        });
    }

    public function openForCashPayment(Payment $payment, $restaurant): ?CashDrawerEvent
    {
        return DB::transaction(function () use ($payment, $restaurant): ?CashDrawerEvent {
            $hasCash = $payment->tenders()->whereHas('method', fn ($q) => $q->where('is_cash', true))->exists();
            if (! $hasCash) {
                return null;
            }
            $event = CashDrawerEvent::create(['restaurant_id' => $restaurant->id, 'cash_session_id' => $payment->cash_session_id, 'payment_id' => $payment->id, 'user_id' => $payment->user_id, 'employee_id' => $payment->employee_id, 'trigger' => 'sale', 'status' => 'requested']);
            $this->dispatchKick($restaurant, $event);

            return $event->fresh();
        });
    }

    private function dispatchKick($restaurant, CashDrawerEvent $event): void
    {
        $printer = $restaurant->printers()->where('is_active', true)->where('open_drawer', true)->first()
            ?? $restaurant->printers()->where('is_active', true)->get()->firstWhere(fn ($printer) => $printer->serves('cash'));
        if (! $printer) {
            $event->update(['status' => 'error', 'error' => 'Sin impresora con cajón configurada.']);

            return;
        }
        try {
            app(PrintService::class)->enqueue('drawer_kick', $restaurant, $printer, ['lines' => [], 'cut' => false, 'open_drawer' => true], ['reference' => 'drawer-'.$event->id, 'order_id' => $event->payment?->order_id]);
            $event->update(['status' => 'opened']);
        } catch (\Throwable $exception) {
            $event->update(['status' => 'error', 'error' => mb_substr($exception->getMessage(), 0, 500)]);
        }
    }
}
