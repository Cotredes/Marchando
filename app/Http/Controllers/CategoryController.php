<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function create(Restaurant $restaurant): View
    {
        $this->authorize('manageCatalog', $restaurant);

        return view('menu.categories.form', ['restaurant' => $restaurant, 'category' => null]);
    }

    public function edit(Restaurant $restaurant, Category $category): View
    {
        $this->authorize('manageCatalog', $restaurant);
        abort_unless($category->restaurant_id === $restaurant->id, 404);

        return view('menu.categories.form', compact('restaurant', 'category'));
    }

    public function store(StoreCategoryRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $position = ((int) $restaurant->categories()->max('position')) + 10;
        $category = $restaurant->categories()->create([
            ...$this->categoryData($request),
            'position' => $position,
        ]);

        if ($request->hasFile('image')) {
            $category->update(['image_path' => $this->storeImage($request, $restaurant, $category->id, 'categories')]);
        }

        return redirect()->route('restaurant.menu.categories.index', $restaurant)->with('status', 'Categoría creada.');
    }

    public function update(StoreCategoryRequest $request, Restaurant $restaurant, Category $category): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        abort_unless($category->restaurant_id === $restaurant->id, 404);
        $oldImage = $category->image_path;
        $data = $this->categoryData($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request, $restaurant, $category->id, 'categories');
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }

        $category->update($data);
        if ($oldImage && $oldImage !== ($data['image_path'] ?? $oldImage)) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('restaurant.menu.categories.index', $restaurant)->with('status', 'Categoría actualizada.');
    }

    public function destroy(Restaurant $restaurant, Category $category): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        abort_unless($category->restaurant_id === $restaurant->id, 404);

        if ($category->products()->exists()) {
            return back()->withErrors(['category' => 'Mueve o elimina sus productos antes de archivar esta categoría.']);
        }

        $category->delete();

        return redirect()->route('restaurant.menu.categories.index', $restaurant)->with('status', 'Categoría archivada.');
    }

    /** @return array<string, mixed> */
    private function categoryData(StoreCategoryRequest $request): array
    {
        return [
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active'),
            'available_dine_in' => $request->boolean('available_dine_in'),
            'available_takeaway' => $request->boolean('available_takeaway'),
            'available_delivery' => $request->boolean('available_delivery'),
        ];
    }

    private function storeImage(StoreCategoryRequest $request, Restaurant $restaurant, int $categoryId, string $type): string
    {
        return $request->file('image')->store("restaurants/{$restaurant->id}/{$type}/{$categoryId}", 'public');
    }
}
