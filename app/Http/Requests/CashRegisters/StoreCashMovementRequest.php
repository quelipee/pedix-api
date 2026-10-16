<?php

namespace App\Http\Requests\CashRegisters;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CashMovementType::class)],
            'payment_method' => ['sometimes', 'required', Rule::in($this->input('type') === CashMovementType::Withdrawal->value
                ? [PaymentMethod::Cash->value, PaymentMethod::Pix->value]
                : [PaymentMethod::Cash->value])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
