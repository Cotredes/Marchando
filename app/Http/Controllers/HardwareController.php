<?php

namespace App\Http\Controllers;

use App\CashDrawerService;
use App\ConnectorService;
use App\Models\CashDrawerEvent;
use App\Models\Order;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Restaurant;
use App\PosService;
use App\PrintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class HardwareController extends Controller
{
    public function printers(Restaurant $restaurant): View
    {
        $this->authorize('manageIntegrations', $restaurant);

        return view('hardware.printers', ['restaurant' => $restaurant, 'printers' => $restaurant->printers()->with('stations')->get(), 'stations' => $restaurant->kitchenStations()->where('is_active', true)->with('printers')->get(), 'connectors' => $restaurant->printConnectors()->get(), 'jobs' => $restaurant->printJobs()->with('printer')->latest('id')->limit(30)->get()]);
    }

    public function storePrinter(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $data = request()->validate(['name' => ['required', 'string', 'max:80'], 'uses' => ['array'], 'uses.*' => ['in:'.implode(',', Printer::USES)], 'paper_width' => ['required', 'in:58,80'], 'connection' => ['required', 'in:network,usb,local'], 'endpoint' => ['nullable', 'string', 'max:160'], 'open_drawer' => ['sometimes', 'boolean']]);
        if ($restaurant->printers()->where('name', $data['name'])->exists()) {
            return back()->withErrors(['name' => 'Ya existe una impresora con ese nombre.'])->withInput();
        }
        $restaurant->printers()->create([...$data, 'uses' => $data['uses'] ?? [], 'open_drawer' => request()->boolean('open_drawer'), 'is_active' => true]);

        return back()->with('status', 'Impresora registrada.');
    }

    public function updatePrinter(Restaurant $restaurant, Printer $printer): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        abort_unless($printer->restaurant_id === $restaurant->id, 404);
        $data = request()->validate(['name' => ['required', 'string', 'max:80'], 'uses' => ['array'], 'uses.*' => ['in:'.implode(',', Printer::USES)], 'paper_width' => ['required', 'in:58,80'], 'connection' => ['required', 'in:network,usb,local'], 'endpoint' => ['nullable', 'string', 'max:160'], 'is_active' => ['sometimes', 'boolean'], 'open_drawer' => ['sometimes', 'boolean']]);
        $printer->update([...$data, 'uses' => $data['uses'] ?? [], 'is_active' => request()->boolean('is_active'), 'open_drawer' => request()->boolean('open_drawer')]);

        return back()->with('status', 'Impresora actualizada.');
    }

    public function mapStation(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $data = request()->validate(['kitchen_station_id' => ['required', 'integer'], 'printer_ids' => ['array'], 'printer_ids.*' => ['integer']]);
        $station = $restaurant->kitchenStations()->findOrFail($data['kitchen_station_id']);
        $printers = $restaurant->printers()->whereIn('id', $data['printer_ids'] ?? [])->pluck('id');
        $sync = [];
        foreach ($printers->values() as $position => $id) {
            $sync[$id] = ['restaurant_id' => $restaurant->id, 'position' => $position];
        }
        $station->printers()->sync($sync);

        return back()->with('status', 'Destinos de la estación actualizados.');
    }

    public function testPrinter(Restaurant $restaurant, Printer $printer): RedirectResponse
    {
        $this->authorize('usePos', $restaurant);
        abort_unless($printer->restaurant_id === $restaurant->id, 404);
        app(PrintService::class)->testPrint($printer, request()->user());

        return back()->with('status', 'Prueba enviada a la cola de impresión.');
    }

    public function reprint(Restaurant $restaurant, PrintJob $printJob): RedirectResponse
    {
        $this->authorize('usePos', $restaurant);
        abort_unless($printJob->restaurant_id === $restaurant->id, 404);
        try {
            $employee = app(PosService::class)->verifyOperator($restaurant, (int) request('employee_id'), (string) request('pin'));
            app(PrintService::class)->reprint($printJob, request()->user(), $employee);
        } catch (\Throwable $exception) {
            return back()->withErrors(['print' => $exception->getMessage()]);
        }

        return back()->with('status', 'Reimpresión encolada y auditada.');
    }

    public function printSaleTicket(Restaurant $restaurant, Order $order): RedirectResponse
    {
        $this->authorize('usePos', $restaurant);
        abort_unless($order->restaurant_id === $restaurant->id && $order->status === 'paid', 404);
        $document = $order->saleDocuments()->where('kind', 'ticket')->first();
        app(PrintService::class)->enqueueCustomerTicket($order, $document, $restaurant, request()->user(), null);

        return back()->with('status', 'Ticket enviado a la cola de impresión.');
    }

    public function retry(Restaurant $restaurant, PrintJob $printJob): RedirectResponse
    {
        $this->authorize('usePos', $restaurant);
        abort_unless($printJob->restaurant_id === $restaurant->id, 404);
        try {
            app(PrintService::class)->retry($printJob);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['print' => $exception->getMessage()]);
        }

        return back()->with('status', 'Trabajo devuelto a la cola.');
    }

    public function createConnector(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manageIntegrations', $restaurant);
        $data = request()->validate(['name' => ['required', 'string', 'max:80']]);
        [$connector, $apiToken, $pairingCode] = app(ConnectorService::class)->create($restaurant, $data['name']);

        return back()->with('status', 'Conector creado.')->with('pairing', ['id' => $connector->id, 'code' => $pairingCode, 'token' => $apiToken]);
    }

    public function openDrawer(Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('usePos', $restaurant);
        try {
            $employee = app(PosService::class)->verifyOperator($restaurant, (int) request('employee_id'), (string) request('pin'));
            $session = $restaurant->cashSessions()->where('status', 'open')->firstOrFail();
            $event = app(CashDrawerService::class)->openManual($restaurant, $session, $employee, request()->user());
        } catch (\Throwable $exception) {
            return back()->withErrors(['drawer' => $exception->getMessage()]);
        }
        if ($event->status === 'error') {
            return back()->withErrors(['drawer' => $event->error ?? 'El cajón no responde; el cobro no se ve afectado.']);
        }

        return back()->with('status', 'Apertura de cajón registrada.');
    }

    public function drawerEvents(Restaurant $restaurant): View
    {
        $this->authorize('usePos', $restaurant);
        $events = CashDrawerEvent::query()->with('employee')->where('restaurant_id', $restaurant->id)->latest('id')->paginate(30);

        return view('hardware.drawer', compact('restaurant', 'events'));
    }
}
