<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachModifierGroupRequest;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductModifierGroup;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductModifierGroupController extends Controller
{
    public function store(AttachModifierGroupRequest $request, Restaurant $restaurant, Product $product): RedirectResponse
    {
        $group = $restaurant->modifierGroups()->findOrFail($request->integer('modifier_group_id'));
        abort_if($product->modifierGroupAssignments()->where('modifier_group_id', $group->id)->exists(), 422, 'El grupo ya está asociado a este producto.');
        $product->modifierGroupAssignments()->create([
            'restaurant_id' => $restaurant->id,
            'modifier_group_id' => $group->id,
            'position' => ((int) $product->modifierGroupAssignments()->max('position')) + 10,
        ]);

        return back()->with('status', 'Grupo asociado al producto.');
    }

    public function destroy(Restaurant $restaurant, Product $product, ModifierGroup $modifierGroup): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureProduct($restaurant, $product);
        abort_unless($modifierGroup->restaurant_id === $restaurant->id, 404);
        $product->modifierGroupAssignments()->where('modifier_group_id', $modifierGroup->id)->delete();

        return back()->with('status', 'Grupo quitado del producto.');
    }

    public function move(Restaurant $restaurant, Product $product, ModifierGroup $modifierGroup, string $direction): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureProduct($restaurant, $product);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        $assignment = $product->modifierGroupAssignments()->where('modifier_group_id', $modifierGroup->id)->firstOrFail();

        DB::transaction(function () use ($product, $assignment, $direction): void {
            $items = $product->modifierGroupAssignments()->lockForUpdate()->get()->values();
            $index = $items->search(fn (ProductModifierGroup $item): bool => $item->is($assignment));
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || ! $items->has($target)) {
                return;
            }
            $ordered = $items->all();
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
            foreach ($ordered as $position => $item) {
                $item->update(['position' => $position * 10]);
            }
        });

        return back()->with('status', 'Orden de grupos actualizado.');
    }

    public function duplicate(Restaurant $restaurant, Product $product, ModifierGroup $modifierGroup): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureProduct($restaurant, $product);
        abort_unless($modifierGroup->restaurant_id === $restaurant->id, 404);
        $assignment = $product->modifierGroupAssignments()->where('modifier_group_id', $modifierGroup->id)->firstOrFail();
        $modifierGroup->load('options');

        DB::transaction(function () use ($restaurant, $modifierGroup, $assignment): void {
            $copy = $restaurant->modifierGroups()->create([
                'name' => $modifierGroup->name.' (copia)',
                'description' => $modifierGroup->description,
                'min_selections' => $modifierGroup->min_selections,
                'max_selections' => $modifierGroup->max_selections,
                'allow_quantities' => $modifierGroup->allow_quantities,
                'is_active' => false,
            ]);
            foreach ($modifierGroup->options as $option) {
                $copy->options()->create([
                    'restaurant_id' => $restaurant->id,
                    'name' => $option->name,
                    'price_delta_minor' => $option->price_delta_minor,
                    'max_quantity' => $option->max_quantity,
                    'instruction' => $option->instruction,
                    'is_active' => $option->is_active,
                    'position' => $option->position,
                ]);
            }
            $assignment->update(['modifier_group_id' => $copy->id]);
        });

        return back()->with('status', 'Grupo duplicado y personalizado para este producto.');
    }

    private function ensureProduct(Restaurant $restaurant, Product $product): void
    {
        abort_unless($product->restaurant_id === $restaurant->id, 404);
    }
}
