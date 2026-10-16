<?php

namespace App\Listeners;

use App\Models\Team;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Events\WebhookHandled;

class SyncSubscriptionPeriod
{
    public function handle(WebhookHandled $event): void
    {
        if (! in_array($event->payload['type'] ?? null, ['customer.subscription.created', 'customer.subscription.updated'], true)) {
            return;
        }

        $data = $event->payload['data']['object'];
        $periodEnd = $data['current_period_end'] ?? null;
        if ($periodEnd === null && is_array($data['items']['data'] ?? null)) {
            foreach ($data['items']['data'] as $item) {
                $candidate = $item['current_period_end'] ?? null;
                if (is_int($candidate) && $candidate > 0 && ($periodEnd === null || $candidate < $periodEnd)) {
                    $periodEnd = $candidate;
                }
            }
        }

        if (! is_int($periodEnd) || $periodEnd <= 0) {
            return;
        }

        $team = Team::query()->where('stripe_id', $data['customer'])->first();
        $team?->subscriptions()->where('stripe_id', $data['id'])->update([
            'current_period_ends_at' => Carbon::createFromTimestampUTC($periodEnd),
        ]);
    }
}
