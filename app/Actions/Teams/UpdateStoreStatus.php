<?php

namespace App\Actions\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class UpdateStoreStatus
{
    public function handle(Team $team, bool $isOpen): void
    {
        DB::transaction(function () use ($team, $isOpen): void {
            // Acquire a write lock before reading the current state on all supported databases.
            Team::query()->whereKey($team->id)->update(['updated_at' => now()]);
            $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->id);
            if ($lockedTeam->is_open && ! $isOpen) {
                $lockedTeam->forceFill([
                    'order_cycle' => $lockedTeam->order_cycle + 1,
                    'next_order_number' => 1,
                ]);
            }
            $lockedTeam->forceFill(['is_open' => $isOpen])->save();
        });
        $team->refresh();
    }
}
