<?php

namespace App\Http\Controllers;

use App\DiningTableQrManager;
use App\Http\Requests\StoreDiningTableBatchRequest;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DiningTableBatchController extends Controller
{
    public function store(StoreDiningTableBatchRequest $request, Restaurant $restaurant, Zone $zone, DiningTableQrManager $qr): RedirectResponse
    {
        $names = $request->names();
        $existing = $zone->diningTables()->withTrashed()->whereIn('name', $names)->exists();
        if ($existing || count($names) !== count(array_unique($names))) {
            return back()->withErrors(['prefix' => 'Alguno de los nombres generados ya existe en esta zona.']);
        }

        DB::transaction(function () use ($request, $zone, $restaurant, $names, $qr): void {
            $position = ((int) $zone->diningTables()->max('position')) + 10;
            foreach ($names as $index => $name) {
                $table = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => $name, 'capacity' => $request->input('capacity'), 'position' => $position + ($index * 10), 'is_active' => $request->boolean('is_active'), 'qr_token' => $qr->newToken()]);
                if ($request->boolean('qr_is_active')) {
                    $qr->activate($table);
                }
            }
        });

        return back()->with('status', count($names).' mesas creadas.');
    }
}
