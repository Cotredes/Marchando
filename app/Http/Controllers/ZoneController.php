<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreZoneRequest;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ZoneController extends Controller
{
    public function store(StoreZoneRequest $request, Restaurant $restaurant): RedirectResponse
    {
        abort_if($restaurant->zones()->where('name', $request->string('name')->toString())->exists(), 422, 'Ya existe una zona con ese nombre.');
        $restaurant->zones()->create(['name' => $request->string('name')->toString(), 'description' => $request->input('description'), 'is_active' => $request->boolean('is_active'), 'position' => ((int) $restaurant->zones()->max('position')) + 10]);

        return back()->with('status', 'Zona creada.');
    }

    public function update(StoreZoneRequest $request, Restaurant $restaurant, Zone $zone): RedirectResponse
    {
        $this->authorize('manage', $zone);
        abort_unless($zone->restaurant_id === $restaurant->id, 404);
        $zone->update(['name' => $request->string('name')->toString(), 'description' => $request->input('description'), 'is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Zona actualizada.');
    }

    public function destroy(Restaurant $restaurant, Zone $zone): RedirectResponse
    {
        $this->authorize('manage', $zone);
        abort_unless($zone->restaurant_id === $restaurant->id, 404);
        if ($zone->diningTables()->exists()) {
            return back()->withErrors(['zone' => 'Mueve o archiva sus mesas antes de eliminar esta zona.']);
        }
        $zone->delete();

        return back()->with('status', 'Zona archivada.');
    }

    public function move(Restaurant $restaurant, Zone $zone, string $direction): RedirectResponse
    {
        $this->authorize('manage', $zone);
        abort_unless($zone->restaurant_id === $restaurant->id && in_array($direction, ['up', 'down'], true), 404);
        DB::transaction(function () use ($restaurant, $zone, $direction): void {
            $zones = $restaurant->zones()->lockForUpdate()->get()->values();
            $index = $zones->search(fn (Zone $item): bool => $item->is($zone));
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || ! $zones->has($target)) {
                return;
            }
            $items = $zones->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
            foreach ($items as $position => $item) {
                $item->update(['position' => $position * 10]);
            }
        });

        return back()->with('status', 'Orden de zonas actualizado.');
    }
}
