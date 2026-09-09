<?php

namespace App\Http\Controllers;

use App\DiningTableQrManager;
use App\Http\Requests\StoreDiningTableRequest;
use App\Http\Requests\TransferDiningTableRequest;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DiningTableController extends Controller
{
    public function store(StoreDiningTableRequest $request, Restaurant $restaurant, Zone $zone, DiningTableQrManager $qr): RedirectResponse
    {
        $position = ((int) $zone->diningTables()->max('position')) + 10;
        $table = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => $request->string('name')->toString(), 'capacity' => $request->input('capacity'), 'position' => $position, 'is_active' => $request->boolean('is_active'), 'qr_token' => $qr->newToken()]);
        if ($request->boolean('qr_is_active')) {
            $qr->activate($table);
        }

        return back()->with('status', 'Mesa creada.');
    }

    public function update(StoreDiningTableRequest $request, Restaurant $restaurant, Zone $zone, DiningTable $diningTable, DiningTableQrManager $qr): RedirectResponse
    {
        $this->authorize('manage', $diningTable);
        $this->ensure($restaurant, $zone, $diningTable);
        $diningTable->update(['name' => $request->string('name')->toString(), 'capacity' => $request->input('capacity'), 'is_active' => $request->boolean('is_active')]);
        $request->boolean('qr_is_active') ? $qr->activate($diningTable) : $qr->revoke($diningTable);

        return back()->with('status', 'Mesa actualizada.');
    }

    public function destroy(Restaurant $restaurant, Zone $zone, DiningTable $diningTable): RedirectResponse
    {
        $this->authorize('manage', $diningTable);
        $this->ensure($restaurant, $zone, $diningTable);
        $diningTable->delete();

        return back()->with('status', 'Mesa archivada.');
    }

    public function move(Restaurant $restaurant, Zone $zone, DiningTable $diningTable, string $direction): RedirectResponse
    {
        $this->authorize('manage', $diningTable);
        $this->ensure($restaurant, $zone, $diningTable);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        DB::transaction(function () use ($zone, $diningTable, $direction): void {
            $tables = $zone->diningTables()->lockForUpdate()->get()->values();
            $index = $tables->search(fn (DiningTable $item): bool => $item->is($diningTable));
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || ! $tables->has($target)) {
                return;
            }
            $items = $tables->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
            foreach ($items as $position => $item) {
                $item->update(['position' => $position * 10]);
            }
        });

        return back()->with('status', 'Orden de mesas actualizado.');
    }

    public function transfer(TransferDiningTableRequest $request, Restaurant $restaurant, Zone $zone, DiningTable $diningTable): RedirectResponse
    {
        $this->ensure($restaurant, $zone, $diningTable);
        $target = $restaurant->zones()->findOrFail($request->integer('target_zone_id'));
        DB::transaction(function () use ($diningTable, $target): void {
            $diningTable->update(['zone_id' => $target->id, 'position' => ((int) $target->diningTables()->max('position')) + 10]);
        });

        return back()->with('status', 'Mesa movida de zona.');
    }

    private function ensure(Restaurant $restaurant, Zone $zone, DiningTable $table): void
    {
        abort_unless($zone->restaurant_id === $restaurant->id && $table->restaurant_id === $restaurant->id && $table->zone_id === $zone->id, 404);
    }
}
