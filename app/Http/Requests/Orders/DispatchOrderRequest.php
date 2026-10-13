<?php

namespace App\Http\Requests\Orders;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DispatchOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['courier_id' => ['required', 'integer', 'exists:couriers,id']];
    }
}
