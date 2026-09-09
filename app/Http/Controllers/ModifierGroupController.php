<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModifierGroupRequest;
use App\Models\ModifierGroup;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModifierGroupController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('viewCatalog', $restaurant);
        $groups = $restaurant->modifierGroups()->withCount(['options', 'productAssignments'])->orderBy('name')->paginate(25);

        return view('menu.modifier-groups.index', compact('restaurant', 'groups'));
    }

    public function create(Restaurant $restaurant): View
    {
        $this->authorize('manageCatalog', $restaurant);

        return view('menu.modifier-groups.form', ['restaurant' => $restaurant, 'modifierGroup' => null]);
    }

    public function edit(Restaurant $restaurant, ModifierGroup $modifierGroup): View
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureGroup($restaurant, $modifierGroup);
        $modifierGroup->load('options');

        return view('menu.modifier-groups.form', compact('restaurant', 'modifierGroup'));
    }

    public function store(ModifierGroupRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $group = DB::transaction(function () use ($request, $restaurant): ModifierGroup {
            $group = $restaurant->modifierGroups()->create($this->groupData($request));
            if ($request->filled('attach_to')) {
                $product = $restaurant->products()->findOrFail($request->integer('attach_to'));
                $product->modifierGroupAssignments()->create([
                    'restaurant_id' => $restaurant->id,
                    'modifier_group_id' => $group->id,
                    'position' => ((int) $product->modifierGroupAssignments()->max('position')) + 10,
                ]);
            }

            return $group;
        });

        if ($request->filled('attach_to')) {
            $product = $restaurant->products()->findOrFail($request->integer('attach_to'));

            return redirect(route('restaurant.menu.products.edit', [$restaurant, $product]).'#modifiers')->with('status', 'Grupo creado y asociado al producto.');
        }

        return redirect()->route('restaurant.menu.modifier-groups.edit', [$restaurant, $group])->with('status', 'Grupo creado. Añade sus opciones.');
    }

    public function update(ModifierGroupRequest $request, Restaurant $restaurant, ModifierGroup $modifierGroup): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureGroup($restaurant, $modifierGroup);
        $modifierGroup->update($this->groupData($request));

        return back()->with('status', 'Grupo actualizado.');
    }

    public function destroy(Restaurant $restaurant, ModifierGroup $modifierGroup): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureGroup($restaurant, $modifierGroup);

        if ($modifierGroup->productAssignments()->exists()) {
            return back()->withErrors(['modifierGroup' => 'Este grupo está asociado a productos. Quítalo de ellos antes de archivarlo.']);
        }

        $modifierGroup->delete();

        return redirect()->route('restaurant.menu.modifier-groups.index', $restaurant)->with('status', 'Grupo archivado.');
    }

    public function duplicate(Restaurant $restaurant, ModifierGroup $modifierGroup): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureGroup($restaurant, $modifierGroup);
        $modifierGroup->load('options');

        $copy = DB::transaction(function () use ($restaurant, $modifierGroup): ModifierGroup {
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

            return $copy;
        });

        return redirect()->route('restaurant.menu.modifier-groups.edit', [$restaurant, $copy])->with('status', 'Grupo duplicado.');
    }

    /** @return array<string, mixed> */
    private function groupData(ModifierGroupRequest $request): array
    {
        return [
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
            'min_selections' => $request->integer('min_selections'),
            'max_selections' => $request->input('max_selections') === null || $request->input('max_selections') === '' ? null : $request->integer('max_selections'),
            'allow_quantities' => $request->boolean('allow_quantities'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function ensureGroup(Restaurant $restaurant, ModifierGroup $modifierGroup): void
    {
        abort_unless($modifierGroup->restaurant_id === $restaurant->id, 404);
    }
}
