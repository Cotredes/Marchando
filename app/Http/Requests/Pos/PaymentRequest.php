<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['request_key' => $this->input('request_key') ?: (string) str()->uuid()]);
    }

    public function rules(): array
    {
        return ['request_key' => ['required', 'string', 'max:120'], 'cash_session_id' => ['required', 'integer'], 'tenders' => ['required', 'array', 'min:1'], 'tenders.*.method_id' => ['required', 'integer'], 'tenders.*.amount_minor' => ['required', 'integer', 'min:1'], 'tenders.*.tendered_minor' => ['nullable', 'integer', 'min:0']];
    }
}
