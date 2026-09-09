<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCatalog', $this->route('restaurant')) ?? false;
    }

    public function rules(): array
    {
        return ['is_available' => ['required', 'boolean']];
    }
}
