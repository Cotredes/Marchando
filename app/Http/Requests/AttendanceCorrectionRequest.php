<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use App\Models\WorkInterval;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $interval = $this->route('interval');

        return $restaurant instanceof Restaurant && $interval instanceof WorkInterval && $interval->restaurant_id === $restaurant->id && $this->user()->can('manageAttendance', $restaurant);
    }

    public function rules(): array
    {
        return ['started_at' => ['required', 'date'], 'ended_at' => ['nullable', 'date'], 'reason' => ['required', 'string', 'min:5', 'max:500']];
    }
}
