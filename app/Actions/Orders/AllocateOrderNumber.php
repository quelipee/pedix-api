<?php

namespace App\Actions\Orders;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class AllocateOrderNumber
{
    /** @return array{number: int, numbering_cycle: int} */
    public function handle(int $teamId): array
    {
        return DB::transaction(function () use ($teamId): array {
            // Reserve the number atomically, including on SQLite where row locks are unavailable.
            Team::query()->whereKey($teamId)->increment('next_order_number');
            $team = Team::query()->lockForUpdate()->findOrFail($teamId);

            return ['number' => $team->next_order_number - 1, 'numbering_cycle' => $team->order_cycle];
        });
    }
}
