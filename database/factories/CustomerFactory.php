<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake('pt_BR')->name(),
            'phone' => fake()->unique()->numerify('(11) 9####-####'),
            'address' => [
                'postal_code' => fake()->numerify('#####-###'),
                'street' => fake('pt_BR')->streetName(),
                'number' => fake()->buildingNumber(),
                'complement' => null,
                'neighborhood' => 'Centro',
                'city' => 'São Paulo',
                'state' => 'SP',
            ],
        ];
    }
}
