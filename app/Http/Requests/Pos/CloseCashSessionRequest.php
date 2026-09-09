<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['declared_cash_minor' => ['required', 'integer', 'min:0'], 'denominations' => ['nullable', 'array'], 'manager_employee_id' => ['required', 'integer'], 'manager_pin' => ['required', 'digits_between:4,8']];
    }
}
