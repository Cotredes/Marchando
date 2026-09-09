<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $product = $this->route('product');

        return $restaurant instanceof Restaurant
            && $this->user()->can('manageCatalog', $restaurant)
            && $product instanceof Product
            && $product->restaurant_id === $restaurant->id;
    }

    public function rules(): array
    {
        $restaurant = $this->route('restaurant');

        return [
            'modifier_group_id' => ['required', 'integer', Rule::exists('modifier_groups', 'id')->where(fn ($query) => $query->where('restaurant_id', $restaurant?->id)->whereNull('deleted_at'))],
        ];
    }
}
