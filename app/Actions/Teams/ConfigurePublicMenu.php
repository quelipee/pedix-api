<?php

namespace App\Actions\Teams;

use App\Models\Team;

class ConfigurePublicMenu
{
    public function handle(Team $team, ?string $deliveryFee): void
    {
        $team->forceFill(['online_delivery_fee' => $deliveryFee])->save();
    }
}
