<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiningTableBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $zone = $this->route('zone');

        return $restaurant instanceof Restaurant && $zone instanceof Zone && $zone->restaurant_id === $restaurant->id && $this->user()->can('manageFloorPlan', $restaurant);
    }

    public function rules(): array
    {
        return ['prefix' => ['required', 'string', 'max:80'], 'start_number' => ['required', 'integer', 'between:1,9999'], 'count' => ['required', 'integer', 'between:1,100'], 'capacity' => ['nullable', 'integer', 'between:1,100'], 'is_active' => ['sometimes', 'boolean'], 'qr_is_active' => ['sometimes', 'boolean'], 'confirm' => ['sometimes', 'boolean']];
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_map(fn (int $number): string => trim($this->string('prefix')->toString()).' '.$number, range($this->integer('start_number'), $this->integer('start_number') + $this->integer('count') - 1));
    }
}
