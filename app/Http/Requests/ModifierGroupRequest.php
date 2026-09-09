<?php

namespace App\Http\Requests;

use App\Models\ModifierGroup;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class ModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $group = $this->route('modifierGroup');

        return $restaurant instanceof Restaurant
            && $this->user()->can('manageCatalog', $restaurant)
            && (! $group || ($group instanceof ModifierGroup && $group->restaurant_id === $restaurant->id));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'min_selections' => ['required', 'integer', 'min:0', 'max:50'],
            'max_selections' => ['nullable', 'integer', 'min:1', 'max:50'],
            'allow_quantities' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'attach_to' => ['nullable', 'integer'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $min = (int) $this->input('min_selections', 0);
            $max = $this->input('max_selections');

            if ($max !== null && $max !== '' && (int) $max < $min) {
                $validator->errors()->add('max_selections', 'El máximo debe ser igual o mayor que el mínimo.');
            }

            if (! $this->boolean('allow_quantities') && $max !== null && (int) $max > 1 && $min > (int) $max) {
                $validator->errors()->add('max_selections', 'La regla de selección no es válida.');
            }
        });
    }
}
