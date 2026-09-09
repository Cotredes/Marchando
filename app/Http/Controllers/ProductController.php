<?php

namespace App\Http\Controllers;

use App\CatalogMoney;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductAvailabilityRequest;
use App\Models\Allergen;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function create(Restaurant $restaurant): View
    {
        $this->authorize('manageCatalog', $restaurant);

        return view('menu.products.form', [
            'restaurant' => $restaurant,
            'product' => null,
            'categories' => $restaurant->categories()->orderBy('position')->get(),
            'allergens' => Allergen::query()->orderBy('position')->get(),
            'modifierGroups' => $restaurant->modifierGroups()->where('is_active', true)->orderBy('name')->get(),
            'stations' => $restaurant->kitchenStations()->where('is_active', true)->get(),
        ]);
    }

    public function edit(Restaurant $restaurant, Product $product): View
    {
        $this->authorize('manageCatalog', $restaurant);
        abort_unless($product->restaurant_id === $restaurant->id, 404);

        return view('menu.products.form', [
            'restaurant' => $restaurant,
            'product' => $product->load(['allergens', 'formats', 'modifierGroupAssignments.group.options']),
            'categories' => $restaurant->categories()->orderBy('position')->get(),
            'allergens' => Allergen::query()->orderBy('position')->get(),
            'modifierGroups' => $restaurant->modifierGroups()->where('is_active', true)->orderBy('name')->get(),
            'stations' => $restaurant->kitchenStations()->where('is_active', true)->get(),
        ]);
    }

    public function store(StoreProductRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $category = $restaurant->categories()->findOrFail($request->integer('category_id'));
        $position = ((int) $category->products()->max('position')) + 10;
        $product = $restaurant->products()->create([
            ...$this->productData($request),
            'category_id' => $category->id,
            'position' => $position,
        ]);
        $product->allergens()->sync($request->input('allergen_ids', []));

        if ($request->hasFile('image')) {
            $product->update(['image_path' => $this->storeImage($request, $restaurant, $product->id)]);
        }

        return redirect()->route('restaurant.menu', $restaurant)->with('status', 'Producto creado.');
    }

    public function update(StoreProductRequest $request, Restaurant $restaurant, Product $product): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        abort_unless($product->restaurant_id === $restaurant->id, 404);
        $oldImage = $product->image_path;
        $oldCategory = $product->category_id;
        $data = $this->productData($request);
        $data['category_id'] = $request->integer('category_id');

        if ($oldCategory !== $data['category_id']) {
            $newCategory = $restaurant->categories()->findOrFail($data['category_id']);
            $data['position'] = ((int) $newCategory->products()->max('position')) + 10;
        }
        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request, $restaurant, $product->id);
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }

        DB::transaction(function () use ($product, $data, $request): void {
            $product->update($data);
            $product->allergens()->sync($request->input('allergen_ids', []));
        });

        if ($oldImage && $oldImage !== ($data['image_path'] ?? $oldImage)) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('restaurant.menu', $restaurant)->with('status', 'Producto actualizado.');
    }

    public function availability(UpdateProductAvailabilityRequest $request, Restaurant $restaurant, Product $product): RedirectResponse
    {
        abort_unless($product->restaurant_id === $restaurant->id, 404);
        $product->update(['is_available' => $request->boolean('is_available')]);

        return back()->with('status', $product->is_available ? 'Producto disponible.' : 'Producto marcado como no disponible.');
    }

    public function destroy(Restaurant $restaurant, Product $product): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        abort_unless($product->restaurant_id === $restaurant->id, 404);
        $product->delete();

        return redirect()->route('restaurant.menu', $restaurant)->with('status', 'Producto archivado.');
    }

    /** @return array<string, mixed> */
    private function productData(StoreProductRequest $request): array
    {
        return [
            'name' => $request->string('name')->toString(),
            'short_name' => $request->input('short_name'),
            'description' => $request->input('description'),
            'price_minor' => CatalogMoney::toMinorUnits($request->input('price')),
            'cost_minor' => CatalogMoney::toMinorUnits($request->input('cost')),
            'vat_rate' => $request->input('vat_rate') === null || $request->input('vat_rate') === '' ? null : $request->input('vat_rate'),
            'is_active' => $request->boolean('is_active'),
            'is_available' => $request->boolean('is_available'),
            'track_stock' => $request->boolean('track_stock'),
            'allows_manual_price' => $request->boolean('allows_manual_price'),
            'kitchen_station_id' => $request->integer('kitchen_station_id') ?: null,
            'available_dine_in' => $request->boolean('available_dine_in'),
            'available_takeaway' => $request->boolean('available_takeaway'),
            'available_delivery' => $request->boolean('available_delivery'),
        ];
    }

    private function storeImage(StoreProductRequest $request, Restaurant $restaurant, int $productId): string
    {
        return $request->file('image')->store("restaurants/{$restaurant->id}/products/{$productId}", 'public');
    }
}
