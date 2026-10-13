<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CashRegister> */
class CashRegisterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'opened_by' => User::factory(),
            'opening_balance' => '100.00',
            'opened_at' => now(),
        ];
    }
}
