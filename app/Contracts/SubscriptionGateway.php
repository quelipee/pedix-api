<?php

namespace App\Contracts;

use App\Data\SubscriptionCheckout;
use App\Enums\BillingPlan;
use App\Models\Team;

interface SubscriptionGateway
{
    /** @return array<string, mixed> */
    public function subscriptionSnapshot(string $subscriptionId): array;

    public function createCheckout(Team $team, BillingPlan $plan, int $trialDays): SubscriptionCheckout;

    public function findCheckout(string $sessionId): SubscriptionCheckout;

    public function expireCheckout(string $sessionId): void;

    public function billingPortal(Team $team): string;
}
