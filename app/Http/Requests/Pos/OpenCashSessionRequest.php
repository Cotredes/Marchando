<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['register_id' => ['required', 'integer'], 'opening_float_minor' => ['required', 'integer', 'min:0'], 'employee_id' => ['required', 'integer'], 'pin' => ['required', 'digits_between:4,8']];
    }
}
