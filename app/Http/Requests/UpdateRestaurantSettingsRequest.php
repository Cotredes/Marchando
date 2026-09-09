<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');

        return $restaurant instanceof Restaurant && $this->user()->can('updateSettings', $restaurant);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'establishment_type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'secondary_phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:100'],
            'tiktok' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'default_vat' => ['nullable', 'numeric', 'between:0,100'],
            'ticket_footer' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone'],
            'dine_in_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
