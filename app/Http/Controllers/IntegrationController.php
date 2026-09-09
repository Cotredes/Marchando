<?php

namespace App\Http\Controllers;

use App\AccountingExportService;
use App\FiscalService;
use App\Models\ChannelPaymentMethod;
use App\Models\OutboundWebhookEndpoint;
use App\Models\Restaurant;
use App\OnlinePaymentService;
use App\OutboundWebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function center(Restaurant $restaurant): View
    {
        $this->authorize('manageIntegrations', $restaurant);
        $stripe = app(OnlinePaymentService::class)->integration($restaurant);
        $aeat = app(FiscalService::class)->integration($restaurant);
        $accounting = app(AccountingExportService::class)->mapping($restaurant);
        $connectors = $restaurant->printConnectors()->get();
        $printers = $restaurant->printers()->get();

        return view('integrations.center', compact('restaurant', 'stripe', 'aeat', 'accounting', 'connectors', 'printers'));
    }

    public function stripeSave(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $data = request()->validate(['mode' => ['required', 'in:test,live'], 'secret_key' => ['nullable', 'string', 'max:200'], 'webhook_secret' => ['nullable', 'string', 'max:200'], 'driver' => ['nullable', 'in:stripe,fake']]);
        $integration = app(OnlinePaymentService::class)->integration($restaurant);
        $secrets = $integration->secrets ?? [];
        if (filled($data['secret_key'] ?? null)) {
            $secrets['secret_key'] = $data['secret_key'];
        }
        if (filled($data['webhook_secret'] ?? null)) {
            $secrets['webhook_secret'] = $data['webhook_secret'];
        }
        $settings = $integration->settings ?? [];
        if (filled($data['driver'] ?? null)) {
            $settings['driver'] = $data['driver'];
        }
        $integration->update(['mode' => $data['mode'], 'secrets' => $secrets, 'settings' => $settings, 'status' => filled($secrets['secret_key'] ?? null) || ($settings['driver'] ?? '') === 'fake' ? 'configured' : 'not_configured', 'last_error' => null]);

        return back()->with('status', 'Integración con Stripe guardada. Las claves nunca se vuelven a mostrar.');
    }

    public function stripeTest(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        app(OnlinePaymentService::class)->testConnection($restaurant);

        return back()->with('status', 'Conexión comprobada.');
    }

    public function stripeDisconnect(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $integration = app(OnlinePaymentService::class)->integration($restaurant);
        $integration->update(['secrets' => [], 'status' => 'not_configured', 'last_error' => null]);

        return back()->with('status', 'Stripe desactivado. El histórico de pagos se conserva.');
    }

    public function channelMethods(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $data = request()->validate(['methods' => ['array']]);
        foreach (ChannelPaymentMethod::CHANNELS as $channel) {
            foreach (ChannelPaymentMethod::METHODS as $method) {
                $enabled = (bool) ($data['methods'][$channel][$method] ?? false);
                if ($method === 'online' && $enabled && ! app(OnlinePaymentService::class)->isConnected($restaurant)) {
                    return back()->withErrors(['methods' => 'No se puede activar el pago online sin Stripe conectado.']);
                }
                $restaurant->channelPaymentMethods()->updateOrCreate(['channel' => $channel, 'method' => $method], ['enabled' => $enabled]);
            }
        }

        return back()->with('status', 'Métodos por canal guardados.');
    }

    public function aeatSave(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $data = request()->validate(['mode' => ['required', 'in:test,live'], 'simulate' => ['required', 'in:ok,reject_once,offline'], 'nif' => ['required', 'string', 'max:20'], 'legal_name' => ['required', 'string', 'max:200']]);
        $integration = app(FiscalService::class)->integration($restaurant);
        $integration->update(['mode' => $data['mode'], 'settings' => ['simulate' => $data['simulate']], 'status' => $data['mode'] === 'live' ? 'configured' : 'test', 'last_error' => null]);
        $restaurant->fiscalIdentities()->update(['is_default' => false]);
        $restaurant->fiscalIdentities()->updateOrCreate(['nif' => mb_strtoupper(trim($data['nif']))], ['legal_name' => $data['legal_name'], 'is_default' => true]);

        return back()->with('status', 'Fiscalidad guardada en modo '.($data['mode'] === 'live' ? 'producción' : 'pruebas').'.');
    }

    public function accountingSave(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $data = request()->validate(['sales_account' => ['nullable', 'string', 'max:20'], 'vat_account' => ['nullable', 'string', 'max:20'], 'customers_account' => ['nullable', 'string', 'max:20'], 'journal' => ['nullable', 'string', 'max:20'], 'notes' => ['nullable', 'string', 'max:500']]);
        app(AccountingExportService::class)->mapping($restaurant)->update($data);

        return back()->with('status', 'Mapeo contable guardado. El perfil SAGE sigue pendiente de validación contra una importación real.');
    }

    public function webhooks(Restaurant $restaurant): View
    {
        $this->authorize('manageIntegrations', $restaurant);

        return view('integrations.webhooks', ['restaurant' => $restaurant, 'endpoints' => $restaurant->webhookEndpoints()->withCount('deliveries')->get(), 'events' => OutboundWebhookEndpoint::EVENTS]);
    }

    public function webhookStore(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $data = request()->validate(['url' => ['required', 'url', 'max:500'], 'events' => ['array'], 'events.*' => ['in:'.implode(',', OutboundWebhookEndpoint::EVENTS)]]);
        $restaurant->webhookEndpoints()->create(['url' => $data['url'], 'signing_secret' => bin2hex(random_bytes(24)), 'events' => $data['events'] ?? [], 'is_active' => true]);

        return back()->with('status', 'Endpoint registrado. El secreto de firma solo se muestra una vez.');
    }

    public function webhookDestroy(Restaurant $restaurant, OutboundWebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        abort_unless($webhookEndpoint->restaurant_id === $restaurant->id, 404);
        $endpoint = $webhookEndpoint;
        $endpoint->delete();

        return back()->with('status', 'Endpoint eliminado. El histórico de entregas se conserva en auditoría.');
    }

    public function webhookRetry(Restaurant $restaurant, OutboundWebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        abort_unless($webhookEndpoint->restaurant_id === $restaurant->id, 404);
        $endpoint = $webhookEndpoint;
        $due = $endpoint->deliveries()->whereIn('status', ['pending', 'error'])->orderBy('id')->limit(20)->get();
        foreach ($due as $delivery) {
            app(OutboundWebhookService::class)->attempt($delivery);
        }

        return back()->with('status', 'Reintentos lanzados.');
    }
}
