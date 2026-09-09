<?php

namespace App\Http\Requests\Pos;

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchPosEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($restaurant = $this->route('restaurant')) instanceof Restaurant && ($order = $this->route('order')) instanceof Order && $order->restaurant_id === $restaurant->id && $this->user()->can('usePos', $restaurant);
    }

    public function rules(): array
    {
        return ['employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('restaurant_id', $this->route('restaurant')?->id)->where('is_active', true)], 'pin' => ['required', 'regex:/^\d{4,8}$/']];
    }
}
