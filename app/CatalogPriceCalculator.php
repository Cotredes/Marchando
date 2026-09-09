<?php

namespace App;

use App\Models\Product;
use App\Models\ProductFormat;
use InvalidArgumentException;

class CatalogPriceCalculator
{
    /**
     * @param  array<int|string, array<int|string, int>>  $selections
     * @return array{currency: string, product_name: string, format_name: string, base_minor: int, adjustments: array<int, array{name: string, quantity: int, unit_minor: int, total_minor: int}>, total_minor: int, vat_rate: ?string}
     */
    public function calculate(Product $product, ?ProductFormat $format, array $selections = []): array
    {
        $product->loadMissing(['restaurant', 'formats', 'modifierGroupAssignments.group.options']);

        if ($product->formats->isNotEmpty()) {
            if (! $format || $format->product_id !== $product->id || ! $format->is_active) {
                throw new InvalidArgumentException('Debes elegir un formato activo del producto.');
            }
            $baseMinor = $format->price_minor;
            $formatName = $format->name;
        } else {
            if ($format) {
                throw new InvalidArgumentException('Este producto no tiene formatos.');
            }
            $baseMinor = $product->price_minor;
            $formatName = 'Precio base';
        }

        $adjustments = [];
        $total = $baseMinor;

        $activeAssignments = $product->modifierGroupAssignments->filter(fn ($assignment) => $assignment->group->is_active)->keyBy('id');
        foreach ($selections as $assignmentId => $selected) {
            if (! $activeAssignments->has((int) $assignmentId)) {
                throw new InvalidArgumentException('La selección de opciones no es válida.');
            }
        }

        foreach ($activeAssignments as $assignment) {
            $selected = $selections[$assignment->id] ?? [];
            $selectedTotal = 0;
            $groupTotal = 0;

            foreach ($selected as $optionId => $quantity) {
                $quantity = (int) $quantity;
                $option = $assignment->group->options->firstWhere('id', (int) $optionId);
                if (! $option || ! $option->is_active || $quantity < 1 || $quantity > $option->max_quantity) {
                    throw new InvalidArgumentException('La selección de opciones no es válida.');
                }
                if (! $assignment->group->allow_quantities && $quantity !== 1) {
                    throw new InvalidArgumentException('Este grupo no permite cantidades.');
                }
                $selectedTotal += $quantity;
                $adjustments[] = [
                    'name' => $option->name,
                    'quantity' => $quantity,
                    'unit_minor' => $option->price_delta_minor,
                    'total_minor' => $option->price_delta_minor * $quantity,
                ];
                $groupTotal += $option->price_delta_minor * $quantity;
            }

            $max = $assignment->group->max_selections;
            if ($selectedTotal < $assignment->group->min_selections || ($max !== null && $selectedTotal > $max)) {
                throw new InvalidArgumentException('La cantidad de opciones elegida no cumple las reglas del grupo.');
            }
            $total += $groupTotal;
        }

        if ($total < 0) {
            throw new InvalidArgumentException('El total no puede ser negativo.');
        }

        return [
            'currency' => $product->restaurant->currency,
            'product_name' => $product->name,
            'format_name' => $formatName,
            'base_minor' => $baseMinor,
            'adjustments' => $adjustments,
            'total_minor' => $total,
            'vat_rate' => $product->effectiveVat(),
        ];
    }
}
