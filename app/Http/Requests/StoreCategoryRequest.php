<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $category = $this->route('category');

        return $restaurant instanceof Restaurant
            && $this->user()->can('manageCatalog', $restaurant)
            && (! $category || ($category instanceof Category && $category->restaurant_id === $restaurant->id));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'available_dine_in' => ['sometimes', 'boolean'],
            'available_takeaway' => ['sometimes', 'boolean'],
            'available_delivery' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }
}
