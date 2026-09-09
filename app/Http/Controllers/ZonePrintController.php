<?php

namespace App\Http\Controllers;

use App\DiningTableQrCode;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\View\View;

class ZonePrintController extends Controller
{
    public function __invoke(Restaurant $restaurant, Zone $zone, DiningTableQrCode $code): View
    {
        $this->authorize('manageFloorPlan', $restaurant);
        abort_unless($zone->restaurant_id === $restaurant->id, 404);
        $zone->load(['diningTables' => fn ($query) => $query->where('is_active', true)->where('qr_is_active', true)]);
        $qrs = $zone->diningTables->map(fn ($table): array => ['table' => $table, 'svg' => $code->svg($table)]);

        return view('restaurant.qr-sheet', compact('restaurant', 'zone', 'qrs'));
    }
}
