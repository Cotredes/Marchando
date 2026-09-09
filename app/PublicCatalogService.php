<?php

namespace App;

use App\Models\DiningTable;
use App\Models\Product;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PublicCatalogService
{
    public function products($restaurant, string $channel): Collection
    {
        abort_unless(in_array($channel, ['dine_in', 'takeaway', 'delivery'], true), 404);
        $flag = 'available_'.$channel;

        return $restaurant->products()
            ->with(['category', 'allergens', 'formats', 'modifierGroupAssignments.group.options'])
            ->where('is_active', true)->where('is_available', true)->where($flag, true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true)->where($flag, true))
            ->whereHas('restaurant', fn ($query) => $query->where($channel === 'dine_in' ? 'dine_in_enabled' : $channel.'_enabled', true)->where('is_active', true))
            ->orderBy('position')->orderBy('id')->get();
    }

    public function product($restaurant, int $productId): Product
    {
        // Un restaurante inactivo no opera públicamente, pero conserva todo.
        abort_unless($restaurant->is_active, 404);

        return $restaurant->products()->with(['category', 'allergens', 'formats', 'modifierGroupAssignments.group.options'])->findOrFail($productId);
    }

    public function quote(Product $product, string $channel, ?int $formatId, array $selections, int $quantity, ?string $notes = null): array
    {
        if (! $product->isEffectivelyAvailable($channel)) {
            throw new InvalidArgumentException('Este producto ya no está disponible.');
        }
        if ($quantity < 1 || $quantity > 20) {
            throw new InvalidArgumentException('La cantidad no es válida.');
        }
        if ($product->track_stock && $product->stock_quantity < $quantity) {
            throw new InvalidArgumentException($product->name.' está agotado o sin stock suficiente.');
        }
        $format = $formatId ? $product->formats->firstWhere('id', $formatId) : null;
        $price = app(CatalogPriceCalculator::class)->calculate($product, $format, $this->normalizeSelections($selections));
        $notes = filled($notes) ? mb_substr(trim($notes), 0, 500) : null;
        $snapshot = ['schema_version' => 1, 'captured_at' => now()->toISOString(), 'product' => ['id' => $product->id, 'name' => $product->name], 'format' => ['id' => $format?->id, 'name' => $price['format_name']], 'quantity' => $quantity, 'currency' => $price['currency'], 'vat_rate' => $price['vat_rate'], 'notes' => $notes, 'modifiers' => $this->modifierSnapshots($product, $selections, $quantity), 'unit_base_minor' => $price['base_minor'], 'unit_modifiers_minor' => $price['total_minor'] - $price['base_minor'], 'unit_total_minor' => $price['total_minor'], 'line_total_minor' => $price['total_minor'] * $quantity];

        return ['product' => $product, 'format' => $format, 'price' => $price, 'quantity' => $quantity, 'notes' => $notes, 'selections' => $this->normalizeSelections($selections), 'snapshot' => $snapshot, 'configuration_key' => sha1(json_encode([$product->id, $format?->id, $selections, $notes], JSON_THROW_ON_ERROR))];
    }

    public function table(string $token): DiningTable
    {
        abort_unless((bool) preg_match('/^[a-f0-9]{64}$/', $token), 404);

        return DiningTable::query()->with(['restaurant', 'zone'])->where('qr_token', $token)->where('qr_is_active', true)->where('is_active', true)->whereHas('zone', fn ($query) => $query->where('is_active', true))->whereHas('restaurant', fn ($query) => $query->where('is_active', true))->firstOrFail();
    }

    public function normalizeSelections(array $selections): array
    {
        $normalized = [];
        foreach ($selections as $assignmentId => $options) {
            if (! is_array($options)) {
                continue;
            }
            foreach ($options as $optionId => $quantity) {
                if ((int) $quantity > 0) {
                    $normalized[(int) $assignmentId][(int) $optionId] = (int) $quantity;
                }
            }
        }

        return $normalized;
    }

    private function modifierSnapshots(Product $product, array $selections, int $quantity): array
    {
        $snapshots = [];
        foreach ($this->normalizeSelections($selections) as $assignmentId => $options) {
            $assignment = $product->modifierGroupAssignments->firstWhere('id', $assignmentId);
            foreach ($options as $optionId => $selectedQuantity) {
                $option = $assignment?->group?->options->firstWhere('id', $optionId);
                if ($option) {
                    $snapshots[] = ['product_modifier_group_id' => $assignment->id, 'modifier_group_id' => $assignment->modifier_group_id, 'modifier_option_id' => $option->id, 'group_name' => $assignment->group->name, 'option_name' => $option->name, 'instruction' => $option->instruction, 'quantity' => $selectedQuantity, 'unit_delta_minor' => $option->price_delta_minor, 'total_delta_minor' => $option->price_delta_minor * $selectedQuantity * $quantity, 'position' => count($snapshots)];
                }
            }
        }

        return $snapshots;
    }
}
