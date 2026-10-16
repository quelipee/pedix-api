<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingPlan;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403);

        $subscription = $team->subscription(config('billing.subscription_type'));
        $configured = filled(config('cashier.key')) && filled(config('cashier.secret')) && filled(config('cashier.webhook.secret'));
        $hasSubscription = $team->hasOngoingSubscription();

        return Inertia::render('billing/Index', [
            'billing' => [
                'configured' => $configured,
                'canManage' => $configured && $team->stripe_id !== null,
                'hasSubscription' => $hasSubscription,
                'trialDays' => $team->subscriptions()->exists() ? 0 : (int) config('billing.trial_days'),
                'status' => $subscription?->stripe_status,
                'trialEndsAt' => $subscription?->trial_ends_at?->toIso8601String(),
                'endsAt' => $subscription?->ends_at?->toIso8601String(),
                'plans' => collect(BillingPlan::cases())->map(fn (BillingPlan $plan): array => [
                    'value' => $plan->value,
                    'name' => config('billing.plans.'.$plan->value.'.name'),
                    'amount' => config('billing.plans.'.$plan->value.'.amount'),
                    'available' => $configured && filled(config('billing.plans.'.$plan->value.'.price_id')),
                    'current' => $hasSubscription && $subscription !== null && filled($subscription->stripe_price)
                        && $subscription->stripe_price === config('billing.plans.'.$plan->value.'.price_id'),
                ])->values(),
            ],
        ]);
    }
}
