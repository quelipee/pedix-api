<?php

namespace App\Services;

use App\Contracts\SubscriptionGateway;
use App\Data\SubscriptionCheckout;
use App\Enums\BillingPlan;
use App\Models\Team;
use Illuminate\Validation\ValidationException;
use LogicException;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripeSubscriptionGateway implements SubscriptionGateway
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function subscriptionSnapshot(string $subscriptionId): array
    {
        return $this->stripe->subscriptions->retrieve($subscriptionId)->toArray();
    }

    public function createCheckout(Team $team, BillingPlan $plan, int $trialDays): SubscriptionCheckout
    {
        $priceId = config('billing.plans.'.$plan->value.'.price_id');
        $price = $this->stripe->prices->retrieve($priceId);

        if (! $price->active || $price->currency !== config('billing.currency')
            || $price->unit_amount !== config('billing.plans.'.$plan->value.'.amount')
            || $price->recurring?->interval !== config('billing.plans.'.$plan->value.'.interval')
            || $price->recurring?->interval_count !== 1) {
            throw ValidationException::withMessages(['plan' => 'O preço deste plano precisa ser revisado antes da contratação.']);
        }

        if ($team->stripe_id === null) {
            $customer = $this->stripe->customers->create([
                'name' => $team->name,
                'email' => $team->stripeEmail(),
                'metadata' => ['team_id' => (string) $team->id],
            ], ['idempotency_key' => 'pedix-customer-'.$team->id]);

            $team->forceFill(['stripe_id' => $customer->id])->save();
        }

        if ($team->billing_checkout_token === null || $team->billing_checkout_expires_at === null) {
            throw new LogicException('A tentativa de checkout deve ser preparada antes de acessar a Stripe.');
        }

        $subscriptionData = ['metadata' => [
            'type' => config('billing.subscription_type'),
            'team_id' => (string) $team->id,
            'plan' => $plan->value,
        ]];

        if ($trialDays > 0) {
            $subscriptionData['trial_period_days'] = $trialDays;
        }

        $session = $this->stripe->checkout->sessions->create([
            'ui_mode' => 'hosted_page',
            'mode' => 'subscription',
            'billing_address_collection' => 'auto',
            'phone_number_collection' => ['enabled' => false],
            'automatic_tax' => ['enabled' => false],
            'allow_promotion_codes' => false,
            'submit_type' => 'auto',
            'integration_identifier' => 'hosted_web_0003',
            'origin_context' => 'web',
            'customer' => $team->stripe_id,
            'client_reference_id' => (string) $team->id,
            'line_items' => [['price' => $priceId, 'quantity' => 1]],
            'payment_method_types' => ['card'],
            'payment_method_collection' => 'always',
            'subscription_data' => $subscriptionData,
            'expires_at' => $team->billing_checkout_expires_at->getTimestamp(),
            'success_url' => route('billing.index', ['current_team' => $team->slug, 'checkout' => 'success']),
            'cancel_url' => route('billing.index', ['current_team' => $team->slug, 'checkout' => 'cancelled']),
        ], ['idempotency_key' => 'pedix-checkout-'.$team->billing_checkout_token]);

        return $this->checkoutData($session);
    }

    public function findCheckout(string $sessionId): SubscriptionCheckout
    {
        return $this->checkoutData($this->stripe->checkout->sessions->retrieve($sessionId));
    }

    public function expireCheckout(string $sessionId): void
    {
        $this->stripe->checkout->sessions->expire($sessionId);
    }

    public function billingPortal(Team $team): string
    {
        return $this->stripe->billingPortal->sessions->create([
            'customer' => $team->stripe_id,
            'return_url' => route('billing.index', ['current_team' => $team->slug]),
        ])->url;
    }

    private function checkoutData(Session $session): SubscriptionCheckout
    {
        return new SubscriptionCheckout($session->id, $session->url, $session->status ?? 'open');
    }
}
