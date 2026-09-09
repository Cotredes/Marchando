<?php

namespace App\Http\Requests;

use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $zone = $this->route('zone');
        $table = $this->route('diningTable');

        return $restaurant instanceof Restaurant
            && $zone instanceof Zone
            && $zone->restaurant_id === $restaurant->id
            && $this->user()->can('manageFloorPlan', $restaurant)
            && (! $table || ($table instanceof DiningTable && $table->restaurant_id === $restaurant->id));
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'capacity' => ['nullable', 'integer', 'between:1,100'], 'is_active' => ['sometimes', 'boolean'], 'qr_is_active' => ['sometimes', 'boolean']];
    }
}
