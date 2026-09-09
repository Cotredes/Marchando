<?php

namespace App\Http\Requests;

use App\CatalogMoney;
use App\Models\Product;
use App\Models\ProductFormat;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class ProductFormatRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $product = $this->route('product');
        $format = $this->route('format');

        return $restaurant instanceof Restaurant
            && $this->user()->can('manageCatalog', $restaurant)
            && $product instanceof Product
            && $product->restaurant_id === $restaurant->id
            && (! $format || ($format instanceof ProductFormat && $format->restaurant_id === $restaurant->id && $format->product_id === $product->id));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'cost' => ['nullable', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function priceMinor(): int
    {
        return CatalogMoney::toMinorUnits($this->input('price')) ?? 0;
    }

    public function costMinor(): ?int
    {
        return CatalogMoney::toMinorUnits($this->input('cost'));
    }
}
