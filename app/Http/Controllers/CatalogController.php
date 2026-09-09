<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogIndexRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(CatalogIndexRequest $request, Restaurant $restaurant): View
    {
        $this->authorize('viewCatalog', $restaurant);

        $filters = $request->validated();
        $categories = $restaurant->categories()->orderBy('position')->orderBy('id')->get();
        $products = $restaurant->products()->with(['category', 'allergens'])->when($filters['q'] ?? null, function ($query, string $search): void {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('short_name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        });

        if (! empty($filters['category'])) {
            $products->where('category_id', $categories->firstWhere('id', (int) $filters['category'])?->id ?? 0);
        }
        if (($filters['status'] ?? null) === 'active') {
            $products->where('is_active', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $products->where('is_active', false);
        }
        if (($filters['availability'] ?? null) === 'available') {
            $products->where('is_available', true);
        } elseif (($filters['availability'] ?? null) === 'unavailable') {
            $products->where('is_available', false);
        }
        if (! empty($filters['channel'])) {
            $channel = $filters['channel'];
            $productColumn = 'available_'.$channel;
            $restaurantEnabled = match ($channel) {
                'dine_in' => $restaurant->dine_in_enabled,
                'takeaway' => $restaurant->takeaway_enabled,
                'delivery' => $restaurant->delivery_enabled,
            };
            $categoryColumn = 'available_'.$channel;
            $products->where($productColumn, true)
                ->whereHas('category', fn ($query) => $query->where($categoryColumn, true)->where('is_active', true));

            if (! $restaurantEnabled) {
                $products->whereRaw('1 = 0');
            }
        }

        $sort = match ($filters['sort'] ?? 'position') {
            'name' => 'name',
            'price' => 'price_minor',
            'updated' => 'updated_at',
            default => 'position',
        };
        $direction = $filters['direction'] ?? 'asc';
        $products = $products->orderBy($sort, $direction)->orderBy('id')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();

        return view('menu.index', [
            'restaurant' => $restaurant,
            'categories' => $categories,
            'products' => $products,
            'filters' => $filters,
            'canManage' => $request->user()->can('manageCatalog', $restaurant),
        ]);
    }

    public function categories(Restaurant $restaurant): View
    {
        $this->authorize('viewCatalog', $restaurant);

        $categories = $restaurant->categories()->withCount(['products', 'products as active_products_count' => fn ($query) => $query->where('is_active', true)])->orderBy('position')->orderBy('id')->get();

        return view('menu.categories.index', [
            'restaurant' => $restaurant,
            'categories' => $categories,
            'canManage' => request()->user()->can('manageCatalog', $restaurant),
        ]);
    }

    public function moveCategory(Restaurant $restaurant, Category $category, string $direction): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        abort_unless($category->restaurant_id === $restaurant->id, 404);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        DB::transaction(function () use ($restaurant, $category, $direction): void {
            $categories = $restaurant->categories()->orderBy('position')->orderBy('id')->get()->values();
            $index = $categories->search(fn (Category $item): bool => $item->is($category));
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! $categories->has($target)) {
                return;
            }

            $items = $categories->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
            foreach ($items as $position => $item) {
                $item->update(['position' => $position * 10]);
            }
        });

        return back()->with('status', 'Orden de categorías actualizado.');
    }

    public function moveProduct(Restaurant $restaurant, Product $product, string $direction): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        abort_unless($product->restaurant_id === $restaurant->id, 404);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        DB::transaction(function () use ($product, $direction): void {
            $products = $product->category->products()->orderBy('position')->orderBy('id')->get()->values();
            $index = $products->search(fn (Product $item): bool => $item->is($product));
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! $products->has($target)) {
                return;
            }

            $items = $products->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
            foreach ($items as $position => $item) {
                $item->update(['position' => $position * 10]);
            }
        });

        return back()->with('status', 'Orden de productos actualizado.');
    }
}
