<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CatalogIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive'],
            'availability' => ['nullable', 'in:available,unavailable'],
            'channel' => ['nullable', 'in:dine_in,takeaway,delivery'],
            'sort' => ['nullable', 'in:position,name,price,updated'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'in:25,50,100'],
        ];
    }
}
