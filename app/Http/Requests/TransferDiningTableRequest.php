<?php

namespace App\Http\Requests;

use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $zone = $this->route('zone');
        $table = $this->route('diningTable');

        return $restaurant instanceof Restaurant && $zone instanceof Zone && $table instanceof DiningTable && $zone->restaurant_id === $restaurant->id && $table->restaurant_id === $restaurant->id && $this->user()->can('manageFloorPlan', $restaurant);
    }

    public function rules(): array
    {
        $restaurant = $this->route('restaurant');

        return ['target_zone_id' => ['required', 'integer', Rule::exists('zones', 'id')->where(fn ($query) => $query->where('restaurant_id', $restaurant?->id)->whereNull('deleted_at'))]];
    }
}
