<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($restaurant = $this->route('restaurant')) instanceof Restaurant && $this->user()->can('manageAttendance', $restaurant);
    }

    public function rules(): array
    {
        return ['employee_id' => [
            'required', 'integer', Rule::exists('employees', 'id')->where('restaurant_id', $this->route('restaurant')?->id)->where('is_active', true),
        ], 'started_at' => ['required', 'date'], 'ended_at' => ['nullable', 'date'], 'reason' => ['required', 'string', 'min:5', 'max:500']];
    }
}
