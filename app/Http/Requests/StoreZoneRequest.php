<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageFloorPlan', $this->route('restaurant')) ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:2000'], 'is_active' => ['sometimes', 'boolean']];
    }
}
