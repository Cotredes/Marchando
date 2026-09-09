<?php

namespace App\Http\Controllers;

use App\FinancialService;
use App\LoyaltyService;
use App\Models\OnlinePaymentIntent;
use App\Models\PublicOrderRequest;
use App\Models\Restaurant;
use App\OnlinePaymentService;
use App\PosService;
use App\PublicOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class PublicOrderAdminController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('usePos', $restaurant);
        $requests = $restaurant->publicOrderRequests()->with(['table.zone', 'lines'])->whereIn('status', ['pending', 'accepted', 'rejected'])->latest()->paginate(30);
        $intents = OnlinePaymentIntent::query()->whereIn('public_order_request_id', $requests->getCollection()->pluck('id'))->get()->keyBy('public_order_request_id');

        return view('orders.index', compact('restaurant', 'requests', 'intents'));
    }

    public function accept(Restaurant $restaurant, PublicOrderRequest $publicOrderRequest): RedirectResponse
    {
        $this->authorize('usePos', $restaurant);
        abort_unless($publicOrderRequest->restaurant_id === $restaurant->id, 404);
        try {
            $employee = app(PosService::class)->verifyOperator($restaurant, (int) request('employee_id'), (string) request('pin'));
            $requestRecord = app(PublicOrderService::class)->accept($publicOrderRequest, $restaurant);
            $requestRecord->update(['accepted_by_user_id' => request()->user()->id, 'accepted_by_employee_id' => $employee->id]);
            $requestRecord->round?->update(['submitted_by_user_id' => request()->user()->id, 'submitted_by_employee_id' => $employee->id, 'created_by_user_id' => request()->user()->id, 'created_by_employee_id' => $employee->id]);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pedido aceptado y enviado a cocina.');
    }

    public function reject(Restaurant $restaurant, PublicOrderRequest $publicOrderRequest): RedirectResponse
    {
        $this->authorize('usePos', $restaurant);
        abort_unless($publicOrderRequest->restaurant_id === $restaurant->id, 404);
        try {
            $intent = app(OnlinePaymentService::class)->intentForRequest($publicOrderRequest);
            if ($intent && $intent->status === 'succeeded') {
                $manager = app(FinancialService::class)->verifyOperator($restaurant, (int) request('employee_id'), (string) request('pin'));
                if (! $manager->operationalRoles->contains('code', 'manager')) {
                    throw new InvalidArgumentException('Rechazar un pedido pagado requiere un encargado.');
                }
                $remaining = $intent->amount_minor - $intent->refundedMinor();
                if ($remaining > 0) {
                    $refund = app(OnlinePaymentService::class)->refund($intent->fresh(), $remaining, 'Rechazo: '.(string) request('reason', 'No disponible'), request()->user(), $manager, 'refund-reject-'.$intent->id);
                    $payment = $intent->order?->payments()->where('status', 'succeeded')->where('reference', $intent->provider_intent_id)->first();
                    if ($payment && ! $payment->reversal()->exists() && $refund->status === 'succeeded') {
                        app(FinancialService::class)->reverse($payment, 'Rechazo de pedido pagado online', 'rev-online-'.$payment->id, $manager, request()->user());
                        app(LoyaltyService::class)->revertForOrder($payment->order, request()->user(), $manager);
                    }
                }
            }
            app(PublicOrderService::class)->reject($publicOrderRequest, (string) request('reason', 'No disponible'), $restaurant);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pedido rechazado.');
    }
}
