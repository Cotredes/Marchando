<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductFormatRequest;
use App\Models\Product;
use App\Models\ProductFormat;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductFormatController extends Controller
{
    public function create(Restaurant $restaurant, Product $product): View
    {
        $this->authorize('manageCatalog', $restaurant);
        abort_unless($product->restaurant_id === $restaurant->id, 404);

        return view('menu.products.formats.form', compact('restaurant', 'product'));
    }

    public function edit(Restaurant $restaurant, Product $product, ProductFormat $format): View
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureFormat($restaurant, $product, $format);

        return view('menu.products.formats.form', compact('restaurant', 'product', 'format'));
    }

    public function store(ProductFormatRequest $request, Restaurant $restaurant, Product $product): RedirectResponse
    {
        $position = ((int) $product->formats()->max('position')) + 10;
        $isDefault = $request->boolean('is_default') || ! $product->formats()->exists();
        $format = $product->formats()->create([
            'restaurant_id' => $restaurant->id,
            'name' => $request->string('name')->toString(),
            'price_minor' => $request->priceMinor(),
            'cost_minor' => $request->costMinor(),
            'position' => $position,
            'is_default' => $isDefault,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($isDefault) {
            $product->formats()->whereKeyNot($format->id)->update(['is_default' => false]);
        }

        return redirect(route('restaurant.menu.products.edit', [$restaurant, $product]).'#formats')->with('status', 'Formato creado.');
    }

    public function update(ProductFormatRequest $request, Restaurant $restaurant, Product $product, ProductFormat $format): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureFormat($restaurant, $product, $format);
        $isDefault = $request->boolean('is_default');

        if ($format->is_default && ! $isDefault) {
            return back()->withErrors(['is_default' => 'Elige otro formato como predeterminado antes de quitar este.']);
        }

        if (! $request->boolean('is_active') && $format->is_default) {
            return back()->withErrors(['is_active' => 'El formato predeterminado debe permanecer activo o debes elegir otro antes.']);
        }

        DB::transaction(function () use ($request, $product, $format, $isDefault): void {
            $format->update([
                'name' => $request->string('name')->toString(),
                'price_minor' => $request->priceMinor(),
                'cost_minor' => $request->costMinor(),
                'is_active' => $request->boolean('is_active'),
                'is_default' => $isDefault,
            ]);
            if ($isDefault) {
                $product->formats()->whereKeyNot($format->id)->update(['is_default' => false]);
            }
        });

        return redirect(route('restaurant.menu.products.edit', [$restaurant, $product]).'#formats')->with('status', 'Formato actualizado.');
    }

    public function destroy(Restaurant $restaurant, Product $product, ProductFormat $format): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureFormat($restaurant, $product, $format);

        if ($product->formats()->whereNull('deleted_at')->count() <= 1) {
            return back()->withErrors(['format' => 'Un producto no puede quedarse sin formatos mientras los usa.']);
        }

        if ($format->is_default) {
            return back()->withErrors(['format' => 'Marca otro formato como predeterminado antes de archivarlo.']);
        }

        $format->delete();

        return back()->with('status', 'Formato archivado.');
    }

    public function move(Restaurant $restaurant, Product $product, ProductFormat $format, string $direction): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureFormat($restaurant, $product, $format);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        DB::transaction(function () use ($product, $format, $direction): void {
            $formats = $product->formats()->orderBy('position')->orderBy('id')->lockForUpdate()->get()->values();
            $index = $formats->search(fn (ProductFormat $item): bool => $item->is($format));
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || ! $formats->has($target)) {
                return;
            }
            $items = $formats->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
            foreach ($items as $position => $item) {
                $item->update(['position' => $position * 10]);
            }
        });

        return back()->with('status', 'Orden de formatos actualizado.');
    }

    public function makeDefault(Restaurant $restaurant, Product $product, ProductFormat $format): RedirectResponse
    {
        $this->authorize('manageCatalog', $restaurant);
        $this->ensureFormat($restaurant, $product, $format);
        abort_unless($format->is_active, 422, 'El formato predeterminado debe estar activo.');

        DB::transaction(function () use ($product, $format): void {
            $product->formats()->whereKeyNot($format->id)->update(['is_default' => false]);
            $format->update(['is_default' => true]);
        });

        return back()->with('status', 'Formato predeterminado actualizado.');
    }

    private function ensureFormat(Restaurant $restaurant, Product $product, ProductFormat $format): void
    {
        abort_unless($product->restaurant_id === $restaurant->id && $format->restaurant_id === $restaurant->id && $format->product_id === $product->id, 404);
    }
}
