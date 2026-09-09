<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Restaurant;
use App\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class StockController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        $this->authorize('viewSales', $restaurant);
        $status = (string) request('status', 'all');
        $products = $restaurant->products()->with('category')
            ->when(request('q'), fn ($q) => $q->where(fn ($inner) => $inner->where('name', 'like', '%'.request('q').'%')->orWhere('short_name', 'like', '%'.request('q').'%')))
            ->when($status === 'tracked', fn ($q) => $q->where('track_stock', true))
            ->when($status === 'low', fn ($q) => $q->where('track_stock', true)->whereColumn('stock_quantity', '<=', 'stock_minimum'))
            ->when($status === 'out', fn ($q) => $q->where('track_stock', true)->where('stock_quantity', '<=', 0))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('analytics.stock', compact('restaurant', 'products', 'status'));
    }

    public function show(Restaurant $restaurant, Product $product): View
    {
        $this->authorize('viewSales', $restaurant);
        abort_unless($product->restaurant_id === $restaurant->id, 404);
        $movements = $product->stockMovements()->with('employee')
            ->when(request('type'), fn ($q) => $q->where('type', request('type')))
            ->latest('id')->paginate(50)->withQueryString();

        return view('analytics.stock-product', compact('restaurant', 'product', 'movements'));
    }

    public function settings(Restaurant $restaurant, Product $product): RedirectResponse
    {
        $this->authorize('manageStock', $restaurant);
        abort_unless($product->restaurant_id === $restaurant->id, 404);
        $data = request()->validate([
            'track_stock' => ['sometimes', 'boolean'], 'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'stock_minimum' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
        try {
            app(StockService::class)->setupTracking($product, request()->boolean('track_stock'), $data['stock_quantity'], $data['stock_minimum'] ?? null, request()->user(), null);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['stock' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Control de stock actualizado.');
    }

    public function entry(Restaurant $restaurant, Product $product): RedirectResponse
    {
        $this->authorize('manageStock', $restaurant);
        abort_unless($product->restaurant_id === $restaurant->id, 404);
        $data = request()->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'reason' => ['nullable', 'string', 'max:500']]);
        try {
            app(StockService::class)->entry($restaurant, $product, $data['quantity'], $data['reason'] ?? null, request()->user(), null);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['stock' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Entrada registrada.');
    }

    public function adjust(Restaurant $restaurant, Product $product): RedirectResponse
    {
        $this->authorize('manageStock', $restaurant);
        abort_unless($product->restaurant_id === $restaurant->id, 404);
        $data = request()->validate(['stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'], 'reason' => ['required', 'string', 'max:500']]);
        try {
            app(StockService::class)->adjustTo($restaurant, $product, $data['stock_quantity'], $data['reason'], request()->user(), null);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['stock' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Stock ajustado con trazabilidad.');
    }
}
