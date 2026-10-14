<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Support\PhoneNumber;

class ResolveOrderCustomer
{
    /** @param array{postal_code: string, street: string, number: string, complement?: string|null, neighborhood: string, city: string, state: string}|null $address */
    public function handle(
        int $teamId,
        ?int $customerId,
        ?string $name,
        ?string $phone,
        ?array $address,
    ): ?Customer {
        if ($customerId !== null) {
            return Customer::query()
                ->where('team_id', $teamId)
                ->findOrFail($customerId);
        }

        $normalizedPhone = PhoneNumber::normalize($phone);

        if ($normalizedPhone === null) {
            return null;
        }

        $customer = Customer::query()->firstOrCreate(
            [
                'team_id' => $teamId,
                'phone_normalized' => $normalizedPhone,
            ],
            [
                'name' => filled($name) ? trim((string) $name) : 'Cliente '.substr($normalizedPhone, -4),
                'phone' => trim((string) $phone),
                'address' => $address,
            ],
        );

        if (filled($name)) {
            $customer->name = trim((string) $name);
        }

        if ($address !== null) {
            $customer->address = $address;
        }

        if ($customer->isDirty()) {
            $customer->save();
        }

        return $customer;
    }
}
