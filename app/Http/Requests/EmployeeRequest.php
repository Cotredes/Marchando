<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');

        return $restaurant instanceof Restaurant && $this->user()->can('manageStaff', $restaurant);
    }

    public function rules(): array
    {
        $restaurant = $this->route('restaurant');
        $employee = $this->route('employee');

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'display_name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::exists('memberships', 'user_id')->where('restaurant_id', $restaurant?->id)],
            'pin' => ['nullable', 'string', 'regex:/^\d{4,8}$/'],
            'remove_pin' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('operational_roles', 'id')->where('restaurant_id', $restaurant?->id)],
        ];
    }

    public function messages(): array
    {
        return ['pin.regex' => 'El PIN debe tener entre 4 y 8 cifras.', 'roles.required' => 'Selecciona al menos una función.'];
    }

    public function pinWasProvided(): bool
    {
        return filled($this->input('pin'));
    }

    public function shouldRemovePin(): bool
    {
        return $this->boolean('remove_pin');
    }
}
