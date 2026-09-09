<?php

namespace App;

use App\Models\Employee;
use App\Models\OrderLine;
use App\Models\OrderRound;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    public function setupTracking(Product $product, bool $track, int $quantity, ?int $minimum, User $user, ?Employee $employee = null): Product
    {
        return DB::transaction(function () use ($product, $track, $quantity, $minimum, $user, $employee): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            if ($quantity < 0 || $quantity > 1000000 || ($minimum !== null && ($minimum < 0 || $minimum > 1000000))) {
                throw new InvalidArgumentException('Las cantidades de stock no son válidas.');
            }
            $wasTracked = $product->track_stock;
            $product->update(['track_stock' => $track, 'stock_quantity' => $quantity, 'stock_minimum' => $minimum]);
            if ($track && ! $wasTracked) {
                $product->stockMovements()->create([
                    'restaurant_id' => $product->restaurant_id, 'user_id' => $user->id, 'employee_id' => $employee?->id,
                    'type' => 'initial', 'quantity_delta' => $quantity, 'resulting_quantity' => $quantity, 'reason' => 'Stock inicial',
                ]);
            }

            return $product->fresh();
        });
    }

    public function entry(Restaurant $restaurant, Product $product, int $quantity, ?string $reason, User $user, ?Employee $employee = null): Product
    {
        return DB::transaction(function () use ($restaurant, $product, $quantity, $reason, $user, $employee): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            abort_unless($product->restaurant_id === $restaurant->id && $product->track_stock, 404);
            if ($quantity < 1 || $quantity > 1000000) {
                throw new InvalidArgumentException('La cantidad de entrada no es válida.');
            }
            $resulting = $product->stock_quantity + $quantity;
            $product->update(['stock_quantity' => $resulting]);
            $product->stockMovements()->create([
                'restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'employee_id' => $employee?->id,
                'type' => 'entry', 'quantity_delta' => $quantity, 'resulting_quantity' => $resulting,
                'reason' => filled($reason) ? mb_substr(trim($reason), 0, 500) : 'Reposición',
            ]);

            return $product->fresh();
        });
    }

    public function adjustTo(Restaurant $restaurant, Product $product, int $newQuantity, string $reason, User $user, ?Employee $employee = null): Product
    {
        return DB::transaction(function () use ($restaurant, $product, $newQuantity, $reason, $user, $employee): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            abort_unless($product->restaurant_id === $restaurant->id && $product->track_stock, 404);
            if ($newQuantity < 0 || $newQuantity > 1000000 || ! filled(trim($reason))) {
                throw new InvalidArgumentException('El ajuste de stock no es válido; indica un motivo.');
            }
            $delta = $newQuantity - $product->stock_quantity;
            if ($delta === 0) {
                throw new InvalidArgumentException('El stock ya coincide con esa cantidad.');
            }
            $product->update(['stock_quantity' => $newQuantity]);
            $product->stockMovements()->create([
                'restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'employee_id' => $employee?->id,
                'type' => $delta > 0 ? 'adjust_in' : 'adjust_out', 'quantity_delta' => $delta, 'resulting_quantity' => $newQuantity,
                'reason' => mb_substr(trim($reason), 0, 500),
            ]);

            return $product->fresh();
        });
    }

    public function assertOrderable(Product $product, int $quantity): void
    {
        if (! $product->track_stock || $quantity < 1) {
            return;
        }
        if ($product->stock_quantity < $quantity) {
            throw new InvalidArgumentException($product->name.' está agotado o sin stock suficiente.');
        }
    }

    public function consumeRound(OrderRound $round, ?User $user = null, ?Employee $employee = null): void
    {
        DB::transaction(function () use ($round, $user, $employee): void {
            $round = OrderRound::query()->with(['lines.product'])->lockForUpdate()->findOrFail($round->id);
            foreach ($round->lines as $line) {
                $product = $line->product;
                if (! $product || ! $product->track_stock) {
                    continue;
                }
                $quantity = $line->activeQuantity();
                if ($quantity < 1) {
                    continue;
                }
                $exists = $product->stockMovements()->where('order_line_id', $line->id)->where('type', 'sale')->exists();
                if ($exists) {
                    continue;
                }
                $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
                if ($locked->stock_quantity < $quantity) {
                    throw new InvalidArgumentException('Sin stock suficiente de '.$locked->name.'.');
                }
                $resulting = $locked->stock_quantity - $quantity;
                $locked->update(['stock_quantity' => $resulting]);
                $locked->stockMovements()->create([
                    'restaurant_id' => $locked->restaurant_id, 'order_id' => $line->order_id, 'order_line_id' => $line->id,
                    'user_id' => $user?->id ?? $line->user_id, 'employee_id' => $employee?->id ?? $line->employee_id,
                    'type' => 'sale', 'quantity_delta' => -$quantity, 'resulting_quantity' => $resulting, 'reason' => 'Venta',
                ]);
            }
        });
    }

    public function restoreLine(OrderLine $line, int $quantity, string $reason, User $user, ?Employee $employee = null): void
    {
        DB::transaction(function () use ($line, $quantity, $reason, $user, $employee): void {
            $line = OrderLine::query()->with('product')->lockForUpdate()->findOrFail($line->id);
            $product = $line->product;
            if (! $product || ! $product->track_stock || $quantity < 1) {
                return;
            }
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $resulting = $locked->stock_quantity + $quantity;
            $locked->update(['stock_quantity' => $resulting]);
            $locked->stockMovements()->create([
                'restaurant_id' => $locked->restaurant_id, 'order_id' => $line->order_id, 'order_line_id' => $line->id,
                'user_id' => $user->id, 'employee_id' => $employee?->id,
                'type' => 'sale_reversal', 'quantity_delta' => $quantity, 'resulting_quantity' => $resulting,
                'reason' => mb_substr(trim($reason) ?: 'Anulación', 0, 500),
            ]);
        });
    }
}
