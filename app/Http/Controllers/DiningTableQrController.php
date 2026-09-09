<?php

namespace App\Http\Controllers;

use App\DiningTableQrCode;
use App\DiningTableQrManager;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class DiningTableQrController extends Controller
{
    public function svg(Restaurant $restaurant, Zone $zone, DiningTable $diningTable, DiningTableQrCode $code): Response
    {
        $this->ensure($restaurant, $zone, $diningTable);
        abort_unless(auth()->user()->can('viewFloorPlan', $restaurant), 403);

        return response($code->svg($diningTable), 200, ['Content-Type' => 'image/svg+xml; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function activate(Restaurant $restaurant, Zone $zone, DiningTable $diningTable, DiningTableQrManager $manager): RedirectResponse
    {
        $this->ensure($restaurant, $zone, $diningTable);
        $this->authorize('manage', $diningTable);
        $manager->activate($diningTable);

        return back()->with('status', 'QR activado.');
    }

    public function revoke(Restaurant $restaurant, Zone $zone, DiningTable $diningTable, DiningTableQrManager $manager): RedirectResponse
    {
        $this->ensure($restaurant, $zone, $diningTable);
        $this->authorize('manage', $diningTable);
        $manager->revoke($diningTable);

        return back()->with('status', 'QR desactivado.');
    }

    public function regenerate(Restaurant $restaurant, Zone $zone, DiningTable $diningTable, DiningTableQrManager $manager): RedirectResponse
    {
        $this->ensure($restaurant, $zone, $diningTable);
        $this->authorize('manage', $diningTable);
        $manager->regenerate($diningTable);

        return back()->with('status', 'QR regenerado. El anterior ya no es válido.');
    }

    private function ensure(Restaurant $restaurant, Zone $zone, DiningTable $table): void
    {
        abort_unless($zone->restaurant_id === $restaurant->id && $table->restaurant_id === $restaurant->id && $table->zone_id === $zone->id, 404);
    }
}
