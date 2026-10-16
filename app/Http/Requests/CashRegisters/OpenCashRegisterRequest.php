<?php

namespace App\Http\Requests\CashRegisters;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OpenCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->current_team_id !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['opening_balance' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2']];
    }
}
