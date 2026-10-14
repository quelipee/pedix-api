<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Support\PhoneNumber;

class CreateCustomer
{
    /** @param array<string, mixed> $data */
    public function handle(int $teamId, array $data): Customer
    {
        return Customer::query()->create([
            ...$data,
            'team_id' => $teamId,
            'phone_normalized' => PhoneNumber::normalize($data['phone']),
        ]);
    }
}
