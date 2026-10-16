<?php

namespace App\Actions\Billing;

use App\Contracts\SubscriptionGateway;
use App\Enums\BillingPlan;
use App\Models\Team;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSubscriptionCheckout
{
    public function __construct(private readonly SubscriptionGateway $gateway) {}

    public function handle(Team $team, BillingPlan $plan): string
    {
        if (blank(config('billing.plans.'.$plan->value.'.price_id'))) {
            throw ValidationException::withMessages(['plan' => 'Este plano ainda não está disponível para contratação.']);
        }

        try {
            return Cache::lock('billing-checkout:'.$team->id, 120)->block(5, function () use ($team, $plan): string {
                $team->refresh();

                if ($team->hasOngoingSubscription()) {
                    throw ValidationException::withMessages(['billing' => 'Esta equipe já tem uma assinatura. Use Gerenciar assinatura para alterá-la.']);
                }

                if ($team->billing_checkout_id !== null) {
                    $checkout = $this->gateway->findCheckout($team->billing_checkout_id);

                    if ($checkout->status === 'complete') {
                        throw ValidationException::withMessages(['billing' => 'Seu checkout foi concluído e está aguardando confirmação da Stripe.']);
                    }

                    if ($checkout->status === 'open' && $checkout->url !== null) {
                        if ($team->billing_checkout_plan === $plan->value) {
                            return $checkout->url;
                        }

                        $this->gateway->expireCheckout($checkout->id);
                    }

                    $team->forceFill([
                        'billing_checkout_id' => null,
                        'billing_checkout_token' => null,
                    ])->save();
                }

                if ($team->billing_checkout_token !== null && $team->billing_checkout_expires_at?->isFuture()
                    && $team->billing_checkout_plan !== $plan->value) {
                    throw ValidationException::withMessages(['billing' => 'Retome o plano anterior para concluir ou encerrar o checkout em andamento.']);
                }

                if ($team->billing_checkout_token === null || ! $team->billing_checkout_expires_at?->isFuture()) {
                    $team->forceFill([
                        'billing_checkout_token' => (string) Str::uuid(),
                        'billing_checkout_plan' => $plan->value,
                        'billing_checkout_expires_at' => now()->addHour(),
                    ])->save();
                }

                $trialDays = $team->subscriptions()->exists() ? 0 : max(0, (int) config('billing.trial_days'));
                $checkout = $this->gateway->createCheckout($team, $plan, $trialDays);

                $team->forceFill(['billing_checkout_id' => $checkout->id])->save();

                if ($checkout->url === null) {
                    throw ValidationException::withMessages(['billing' => 'Não foi possível abrir o checkout. Tente novamente.']);
                }

                return $checkout->url;
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['billing' => 'Há uma contratação em andamento. Aguarde e tente novamente.']);
        }
    }
}
