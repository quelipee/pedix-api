<?php

namespace App\Http\Requests\CashRegisters;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloseCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['closing_balance' => ['required', 'numeric', 'between:-99999999.99,99999999.99', 'decimal:0,2']];
    }
}
