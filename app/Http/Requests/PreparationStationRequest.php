<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class PreparationStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');

        return $restaurant instanceof Restaurant && $this->user()->can('manageKitchen', $restaurant);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'is_active' => ['sometimes', 'boolean'], 'warning_after_seconds' => ['required', 'integer', 'min:60', 'max:86400'], 'late_after_seconds' => ['required', 'integer', 'min:60', 'max:172800']];
    }
}
