<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModifierOptionRequest;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModifierOptionController extends Controller
{
    public function create(Restaurant $restaurant, ModifierGroup $modifierGroup): View
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureGroup($restaurant, $modifierGroup);

        return view('menu.modifier-groups.options.form', compact('restaurant', 'modifierGroup'));
    }

    public function edit(Restaurant $restaurant, ModifierGroup $modifierGroup, ModifierOption $modifierOption): View
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureOption($restaurant, $modifierGroup, $modifierOption);

        return view('menu.modifier-groups.options.form', compact('restaurant', 'modifierGroup', 'modifierOption'));
    }

    public function store(ModifierOptionRequest $request, Restaurant $restaurant, ModifierGroup $modifierGroup): RedirectResponse
    {
        $this->ensureGroup($restaurant, $modifierGroup);
        $modifierGroup->options()->create([
            ...$this->optionData($request),
            'restaurant_id' => $restaurant->id,
            'position' => ((int) $modifierGroup->options()->max('position')) + 10,
        ]);

        return redirect(route('restaurant.menu.modifier-groups.edit', [$restaurant, $modifierGroup]).'#options')->with('status', 'Opción añadida.');
    }

    public function update(ModifierOptionRequest $request, Restaurant $restaurant, ModifierGroup $modifierGroup, ModifierOption $modifierOption): RedirectResponse
    {
        $this->ensureOption($restaurant, $modifierGroup, $modifierOption);
        $modifierOption->update($this->optionData($request));

        return redirect(route('restaurant.menu.modifier-groups.edit', [$restaurant, $modifierGroup]).'#options')->with('status', 'Opción actualizada.');
    }

    public function destroy(Restaurant $restaurant, ModifierGroup $modifierGroup, ModifierOption $modifierOption): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureOption($restaurant, $modifierGroup, $modifierOption);

        if ($modifierGroup->options()->where('is_active', true)->whereKeyNot($modifierOption->id)->count() < $modifierGroup->min_selections) {
            return back()->withErrors(['modifierOption' => 'No puedes archivar esta opción porque el mínimo del grupo dejaría de poder cumplirse.']);
        }

        $modifierOption->delete();

        return back()->with('status', 'Opción archivada.');
    }

    public function move(Restaurant $restaurant, ModifierGroup $modifierGroup, ModifierOption $modifierOption, string $direction): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureOption($restaurant, $modifierGroup, $modifierOption);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        DB::transaction(function () use ($modifierGroup, $modifierOption, $direction): void {
            $options = $modifierGroup->options()->lockForUpdate()->get()->values();
            $index = $options->search(fn (ModifierOption $item): bool => $item->is($modifierOption));
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || ! $options->has($target)) {
                return;
            }
            $items = $options->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
            foreach ($items as $position => $item) {
                $item->update(['position' => $position * 10]);
            }
        });

        return back()->with('status', 'Orden de opciones actualizado.');
    }

    /** @return array<string, mixed> */
    private function optionData(ModifierOptionRequest $request): array
    {
        return [
            'name' => $request->string('name')->toString(),
            'price_delta_minor' => $request->supplementMinor(),
            'max_quantity' => $request->integer('max_quantity'),
            'instruction' => $request->string('instruction')->toString(),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function ensureGroup(Restaurant $restaurant, ModifierGroup $modifierGroup): void
    {
        abort_unless($modifierGroup->restaurant_id === $restaurant->id, 404);
    }

    private function ensureOption(Restaurant $restaurant, ModifierGroup $modifierGroup, ModifierOption $modifierOption): void
    {
        $this->ensureGroup($restaurant, $modifierGroup);
        abort_unless($modifierOption->restaurant_id === $restaurant->id && $modifierOption->modifier_group_id === $modifierGroup->id, 404);
    }
}
