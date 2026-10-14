<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderModality;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
            'modality' => ['required', Rule::enum(OrderModality::class)],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('team_id', $this->user()->current_team_id),
            ],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'needs_change' => ['exclude_unless:payment_method,dinheiro', 'boolean'],
            'change_for' => ['exclude_unless:payment_method,dinheiro', 'nullable', 'required_if:needs_change,true', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'required_if:modality,entrega', 'string', 'max:20'],
            'delivery_address' => ['required_if:modality,entrega', 'array'],
            'delivery_address.postal_code' => ['nullable', 'required_if:modality,entrega', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'delivery_address.street' => ['nullable', 'required_if:modality,entrega', 'string', 'max:255'],
            'delivery_address.number' => ['nullable', 'required_if:modality,entrega', 'string', 'max:20'],
            'delivery_address.complement' => ['nullable', 'string', 'max:120'],
            'delivery_address.neighborhood' => ['nullable', 'required_if:modality,entrega', 'string', 'max:120'],
            'delivery_address.city' => ['nullable', 'required_if:modality,entrega', 'string', 'max:120'],
            'delivery_address.state' => ['nullable', 'required_if:modality,entrega', 'string', 'size:2'],
            'delivery_fee' => ['exclude_unless:modality,entrega', 'required', 'numeric', 'min:0', 'decimal:0,2', 'max:99999999.99'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('active', true)->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.option_ids' => ['sometimes', 'array', 'max:100'],
            'items.*.option_ids.*' => ['integer', 'distinct'],
        ];
    }
}
