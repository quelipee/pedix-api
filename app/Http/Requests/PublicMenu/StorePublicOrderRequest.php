<?php

namespace App\Http\Requests\PublicMenu;

use App\Enums\PaymentMethod;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Team $store */
        $store = $this->route('store');
        $tokens = array_keys(array_filter($this->session()->get('public_checkout_tokens', []), fn ($teamId): bool => $teamId === $store->id));

        return [
            'checkout_token' => ['required', 'uuid', Rule::in($tokens)],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^(?=(?:\D*\d){10,13}\D*$)[0-9()+\s-]+$/'],
            'modality' => ['required', Rule::in(['retirada', 'entrega'])],
            'payment_method' => ['required', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Pix->value, PaymentMethod::Card->value])],
            'needs_change' => ['exclude_unless:payment_method,dinheiro', 'boolean'],
            'change_for' => ['exclude_unless:payment_method,dinheiro', 'nullable', 'required_if:needs_change,true', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'delivery_address' => ['exclude_unless:modality,entrega', 'required', 'array:postal_code,street,number,complement,neighborhood,city,state'],
            'delivery_address.postal_code' => ['exclude_unless:modality,entrega', 'required', 'regex:/^\d{5}-?\d{3}$/'],
            'delivery_address.street' => ['exclude_unless:modality,entrega', 'required', 'string', 'max:255'],
            'delivery_address.number' => ['exclude_unless:modality,entrega', 'required', 'string', 'max:20'],
            'delivery_address.complement' => ['exclude_unless:modality,entrega', 'nullable', 'string', 'max:120'],
            'delivery_address.neighborhood' => ['exclude_unless:modality,entrega', 'required', 'string', 'max:120'],
            'delivery_address.city' => ['exclude_unless:modality,entrega', 'required', 'string', 'max:120'],
            'delivery_address.state' => ['exclude_unless:modality,entrega', 'required', 'string', 'size:2'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('team_id', $store->id)->where('active', true)->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.option_ids' => ['sometimes', 'array', 'max:100'],
            'items.*.option_ids.*' => ['integer', 'distinct'],
        ];
    }
}
