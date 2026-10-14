<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->current_team_id !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = Customer::query()
                        ->where('team_id', $this->user()->current_team_id)
                        ->where('phone_normalized', PhoneNumber::normalize((string) $value))
                        ->exists();

                    if ($exists) {
                        $fail('Já existe um cliente com este telefone.');
                    }
                },
            ],
            'address' => ['required', 'array'],
            'address.postal_code' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'address.street' => ['required', 'string', 'max:255'],
            'address.number' => ['required', 'string', 'max:20'],
            'address.complement' => ['nullable', 'string', 'max:120'],
            'address.neighborhood' => ['required', 'string', 'max:120'],
            'address.city' => ['required', 'string', 'max:120'],
            'address.state' => ['required', 'string', 'size:2'],
        ];
    }
}
