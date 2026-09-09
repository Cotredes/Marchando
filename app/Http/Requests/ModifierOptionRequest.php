<?php

namespace App\Http\Requests;

use App\CatalogMoney;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class ModifierOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $group = $this->route('modifierGroup');
        $option = $this->route('modifierOption');

        return $restaurant instanceof Restaurant
            && $this->user()->can('manageCatalog', $restaurant)
            && $group instanceof ModifierGroup
            && $group->restaurant_id === $restaurant->id
            && (! $option || ($option instanceof ModifierOption && $option->restaurant_id === $restaurant->id && $option->modifier_group_id === $group->id));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'supplement' => ['nullable', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'max_quantity' => ['required', 'integer', 'between:1,20'],
            'instruction' => ['required', 'in:normal,highlight,aside'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function supplementMinor(): int
    {
        return CatalogMoney::toMinorUnits($this->input('supplement', '0')) ?? 0;
    }
}
