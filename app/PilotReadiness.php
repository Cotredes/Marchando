<?php

namespace App;

class PilotReadiness
{
    /**
     * @return array<int, array{key:string,label:string,ok:bool,detail:string,link:?string}>
     */
    public function check($restaurant): array
    {
        $restaurant->loadMissing(['openingHours', 'categories', 'products', 'zones', 'diningTables', 'employees', 'kitchenStations', 'cashRegisters', 'paymentMethods', 'printers', 'printConnectors', 'fiscalIdentities']);
        $checks = [];

        $activeProducts = $restaurant->products()->where('is_active', true)->count();
        $productsNoPrice = $restaurant->products()->where('is_active', true)->where('price_minor', '<', 1)->count();
        $productsNoVat = filled($restaurant->default_vat) ? 0 : $restaurant->products()->where('is_active', true)->whereNull('vat_rate')->count();
        $checks[] = $this->row('carta', 'Carta con productos, precio e IVA', $activeProducts > 0 && $productsNoPrice === 0 && $productsNoVat === 0, $activeProducts.' activos'.($productsNoPrice ? ' · '.$productsNoPrice.' sin precio' : '').($productsNoVat ? ' · '.$productsNoVat.' sin IVA' : ''), 'restaurant.menu');

        $tables = $restaurant->diningTables()->where('is_active', true)->count();
        $tablesNoQr = $restaurant->diningTables()->where('is_active', true)->where('qr_is_active', false)->count();
        $checks[] = $this->row('mesas', 'Mesas operativas con QR', $tables > 0, $tables.' mesas'.($tablesNoQr ? ' · '.$tablesNoQr.' sin QR activo' : ''), 'restaurant.restaurant');

        $staff = $restaurant->employees()->where('is_active', true)->whereNotNull('pin_hash')->count();
        $checks[] = $this->row('personal', 'Personal operativo con PIN', $staff > 0, $staff.' empleados con PIN', 'restaurant.staff');

        $stations = $restaurant->kitchenStations()->where('is_active', true)->count();
        $unrouted = $restaurant->products()->where('is_active', true)->whereNull('kitchen_station_id')->count();
        $checks[] = $this->row('cocina', 'Cocina con estaciones', $stations > 0, $stations.' estaciones'.($unrouted ? ' · '.$unrouted.' productos van a «Sin asignar»' : ''), 'restaurant.kitchen.manage');

        $registers = $restaurant->cashRegisters()->where('is_active', true)->count();
        $openSession = $restaurant->cashSessions()->where('status', 'open')->exists();
        $checks[] = $this->row('caja', 'Caja configurada', $registers > 0, $registers.' cajas'.($openSession ? ' · sesión abierta' : ' · sin sesión abierta'), 'restaurant.pos.cash');

        $methods = $restaurant->paymentMethods()->where('is_active', true)->count();
        $checks[] = $this->row('pagos', 'Métodos de pago', $methods > 0, $methods.' métodos activos', 'restaurant.integrations');

        $hours = $restaurant->openingHours()->count();
        $checks[] = $this->row('horarios', 'Horario general definido', $hours > 0, $hours.' franjas semanales', 'restaurant.settings');

        $fiscal = $restaurant->fiscalIdentities()->count() > 0 || filled($restaurant->tax_id);
        $checks[] = $this->row('fiscalidad', 'Identidad fiscal (NIF)', $fiscal, $fiscal ? 'obligado identificado' : 'falta NIF del obligado tributario', 'restaurant.integrations');

        $printers = $restaurant->printers()->where('is_active', true)->count();
        $onlineConnector = $restaurant->printConnectors()->where('is_active', true)->get()->firstWhere(fn ($c) => $c->isOnline());
        $checks[] = $this->row('impresion', 'Impresión operativa', $printers > 0, $printers.' impresoras'.($onlineConnector ? ' · conector en línea' : ' · sin conector en línea (KDS sigue funcionando)'), 'restaurant.hardware');

        return $checks;
    }

    public function attention($restaurant): array
    {
        $alerts = [];
        $fiscalBad = $restaurant->fiscalRecords()->whereIn('status', ['rejected', 'error'])->count();
        if ($fiscalBad > 0) {
            $alerts[] = ['label' => $fiscalBad.' registros fiscales con incidencia', 'link' => 'restaurant.fiscal'];
        }
        $printBad = $restaurant->printJobs()->where('status', 'error')->count();
        if ($printBad > 0) {
            $alerts[] = ['label' => $printBad.' trabajos de impresión en error', 'link' => 'restaurant.hardware'];
        }
        $pending = $restaurant->publicOrderRequests()->where('status', 'pending')->count();
        if ($pending > 0) {
            $alerts[] = ['label' => $pending.' pedidos públicos pendientes de aceptar', 'link' => 'restaurant.orders'];
        }
        $lowStock = $restaurant->products()->where('track_stock', true)->whereColumn('stock_quantity', '<=', 'stock_minimum')->count();
        if ($lowStock > 0) {
            $alerts[] = ['label' => $lowStock.' productos en stock bajo o agotados', 'link' => 'restaurant.stock'];
        }

        return $alerts;
    }

    private function row(string $key, string $label, bool $ok, string $detail, ?string $link): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'detail' => $detail, 'link' => $link];
    }
}
