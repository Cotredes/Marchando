<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantChannelsRequest extends FormRequest
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
            'dine_in_enabled' => ['sometimes', 'boolean'],
            'takeaway_enabled' => ['sometimes', 'boolean'],
            'takeaway_prep_minutes' => ['nullable', 'integer', 'between:1,240'],
            'takeaway_use_general_schedule' => ['sometimes', 'boolean'],
            'delivery_enabled' => ['sometimes', 'boolean'],
            'delivery_radius_km' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'delivery_fee' => ['nullable', 'numeric', 'gte:0', 'max:10000'],
            'delivery_minimum_order' => ['nullable', 'numeric', 'gte:0', 'max:10000'],
            'delivery_prep_minutes' => ['nullable', 'integer', 'between:1,240'],
            'delivery_use_general_schedule' => ['sometimes', 'boolean'],
            'public_menu_enabled' => ['sometimes', 'boolean'],
            'qr_ordering_enabled' => ['sometimes', 'boolean'],
            'qr_acceptance_mode' => ['required', 'in:manual,automatic'],
            'takeaway_acceptance_mode' => ['required', 'in:manual,automatic'],
            'delivery_acceptance_mode' => ['required', 'in:manual,automatic'],
            'takeaway_scheduling_enabled' => ['sometimes', 'boolean'],
            'delivery_scheduling_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
