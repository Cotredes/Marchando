<?php

namespace App\Http\Controllers;

use App\FinancialService;
use App\LoyaltyService;
use App\Models\OnlinePaymentIntent;
use App\Models\PublicOrderRequest;
use App\Models\Restaurant;
use App\OnlinePayments\FakeOnlineProvider;
use App\OnlinePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;

class OnlinePaymentController extends Controller
{
    private function requestFromToken(string $token): PublicOrderRequest
    {
        return PublicOrderRequest::query()->where('public_token_hash', hash('sha256', $token))->with('restaurant')->firstOrFail();
    }

    public function create(string $token): RedirectResponse
    {
        $request = $this->requestFromToken($token);
        try {
            $intent = app(OnlinePaymentService::class)->createIntent($request->restaurant, $request);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('public.order.track', ['token' => $token])->withErrors(['online' => $exception->getMessage()]);
        }
        if ($intent->checkout_url) {
            return redirect()->away($intent->checkout_url);
        }

        return redirect()->route('public.order.fake', ['token' => $token]);
    }

    public function fake(string $token): View
    {
        $request = $this->requestFromToken($token);
        $intent = app(OnlinePaymentService::class)->intentForRequest($request);
        abort_unless($intent && $intent->status !== 'succeeded', 404);
        abort_unless(app(OnlinePaymentService::class)->provider($request->restaurant) instanceof FakeOnlineProvider, 404);

        return view('online.fake', ['request' => $request, 'intent' => $intent, 'restaurant' => $request->restaurant]);
    }

    public function fakeConfirm(string $token): RedirectResponse
    {
        $request = $this->requestFromToken($token);
        $intent = app(OnlinePaymentService::class)->intentForRequest($request);
        abort_unless($intent, 404);
        abort_unless(app(OnlinePaymentService::class)->provider($request->restaurant) instanceof FakeOnlineProvider, 404);
        if (request('result') === 'fail') {
            $intent->update(['status' => 'failed', 'failure_reason' => 'Pago rechazado por el proveedor (simulación).']);
        } else {
            app(OnlinePaymentService::class)->markIntentSucceeded($intent);
        }

        return redirect()->route('public.order.track', ['token' => $token]);
    }

    public function paidReturn(string $token): View
    {
        $request = $this->requestFromToken($token);
        $intent = app(OnlinePaymentService::class)->intentForRequest($request);
        if ($intent && $intent->status === 'processing') {
            try {
                $state = app(OnlinePaymentService::class)->provider($request->restaurant)->retrieveIntent($intent->provider_intent_id);
                if (($state['status'] ?? '') === 'succeeded') {
                    app(OnlinePaymentService::class)->markIntentSucceeded($intent);
                }
            } catch (InvalidArgumentException) {
            }
        }

        return view('public.track', ['request' => $request->fresh()->load('restaurant'), 'intent' => $intent?->fresh(), 'onlineAllowed' => app(OnlinePaymentService::class)->onlineAllowed($request->restaurant, $request->channel)]);
    }

    public function webhook(Request $request, string $restaurant): Response
    {
        $model = Restaurant::query()->where('slug', $restaurant)->firstOrFail();
        $payload = $request->getContent();
        try {
            app(OnlinePaymentService::class)->handleWebhook($model, 'stripe', $payload, (string) $request->header('Stripe-Signature', ''));
        } catch (InvalidArgumentException $exception) {
            return response(['message' => $exception->getMessage()], 400);
        }

        return response(['received' => true], 200);
    }

    public function refund(Restaurant $restaurant, OnlinePaymentIntent $onlineIntent): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $intent = $onlineIntent;
        abort_unless($intent->restaurant_id === $restaurant->id, 404);
        $data = request()->validate(['amount_minor' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:500'], 'employee_id' => ['required', 'integer'], 'pin' => ['required', 'string']]);
        try {
            $manager = app(FinancialService::class)->verifyOperator($restaurant, (int) $data['employee_id'], (string) $data['pin']);
            if (! $manager->operationalRoles->contains('code', 'manager')) {
                throw new InvalidArgumentException('La devolución requiere un encargado.');
            }
            $refund = app(OnlinePaymentService::class)->refund($intent->fresh(), (int) $data['amount_minor'], (string) $data['reason'], request()->user(), $manager, 'refund-'.$intent->id.'-'.(int) $data['amount_minor'].'-'.time());
            $payment = $intent->order?->payments()->where('status', 'succeeded')->where('reference', $intent->provider_intent_id)->first();
            if ($payment && ! $payment->reversal()->exists() && $refund->status === 'succeeded' && $refund->amount_minor >= $payment->amount_minor) {
                app(FinancialService::class)->reverse($payment, (string) $data['reason'], 'rev-online-'.$payment->id, $manager, request()->user());
                app(LoyaltyService::class)->revertForOrder($payment->order, request()->user(), $manager);
            }
            $intent->order?->events()->create(['restaurant_id' => $restaurant->id, 'user_id' => request()->user()->id, 'employee_id' => $manager->id, 'type' => 'online_refunded', 'data' => ['intent_id' => $intent->id, 'amount_minor' => $refund->amount_minor, 'reason' => $data['reason']]]);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with('status', 'Reembolso procesado y auditado.');
    }
}
