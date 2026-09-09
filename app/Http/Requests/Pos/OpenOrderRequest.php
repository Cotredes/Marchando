<?php

namespace App\Http\Requests\Pos;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($restaurant = $this->route('restaurant')) instanceof Restaurant && $this->user()->can('usePos', $restaurant);
    }

    public function rules(): array
    {
        return ['employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('restaurant_id', $this->route('restaurant')?->id)->where('is_active', true)], 'pin' => ['required', 'regex:/^\d{4,8}$/'], 'guest_count' => ['nullable', 'integer', 'min:1', 'max:999']];
    }
}
