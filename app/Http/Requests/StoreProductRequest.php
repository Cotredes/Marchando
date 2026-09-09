<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $product = $this->route('product');

        return $restaurant instanceof Restaurant
            && $this->user()->can('manageCatalog', $restaurant)
            && (! $product || ($product instanceof Product && $product->restaurant_id === $restaurant->id));
    }

    public function rules(): array
    {
        $restaurant = $this->route('restaurant');

        return [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('restaurant_id', $restaurant?->id)->whereNull('deleted_at'))],
            'price' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'cost' => ['nullable', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'vat_rate' => ['nullable', 'numeric', 'between:0,100'],
            'is_active' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
            'track_stock' => ['sometimes', 'boolean'],
            'allows_manual_price' => ['sometimes', 'boolean'],
            'kitchen_station_id' => ['nullable', 'integer', Rule::exists('kitchen_stations', 'id')->where('restaurant_id', $restaurant?->id)->whereNull('deleted_at')],
            'available_dine_in' => ['sometimes', 'boolean'],
            'available_takeaway' => ['sometimes', 'boolean'],
            'available_delivery' => ['sometimes', 'boolean'],
            'allergen_ids' => ['nullable', 'array'],
            'allergen_ids.*' => ['integer', 'distinct', 'exists:allergens,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $restaurant = $this->route('restaurant');

            if ($this->input('vat_rate') === null && $restaurant?->default_vat === null) {
                $validator->errors()->add('vat_rate', 'Configura un IVA en el producto o en el restaurante.');
            }
        });
    }
}
