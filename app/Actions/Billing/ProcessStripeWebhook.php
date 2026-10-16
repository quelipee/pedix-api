<?php

namespace App\Actions\Billing;

use App\Contracts\SubscriptionGateway;
use App\Models\Team;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class ProcessStripeWebhook
{
    public function __construct(private readonly SubscriptionGateway $gateway) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(array<string, mixed>): Response  $process
     */
    public function handle(array $payload, Closure $process): Response
    {
        if (! in_array($payload['type'], ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            return $process($payload);
        }

        $id = data_get($payload, 'data.object.id');
        $customer = data_get($payload, 'data.object.customer');
        abort_unless(is_string($id) && str_starts_with($id, 'sub_') && is_string($customer), 400);

        if (! Team::query()->where('stripe_id', $customer)->exists()) {
            return response('Webhook handled', 200);
        }

        return Cache::lock('stripe-subscription:'.$id, 300)->block(5, function () use ($payload, $id, $customer, $process): Response {
            if (DB::table('stripe_webhook_receipts')->where('event_id', $payload['id'])->exists()) {
                return response('Webhook handled', 200);
            }

            $current = $this->gateway->subscriptionSnapshot($id);
            if (($current['id'] ?? null) !== $id || ($current['customer'] ?? null) !== $customer) {
                throw new UnexpectedValueException('Stripe subscription identity mismatch.');
            }

            // Cashier can use Stripe's explicit cancellation date without fetching the subscription again.
            if (($current['cancel_at_period_end'] ?? false) && is_int($current['cancel_at'] ?? null)) {
                $current['cancel_at_period_end'] = false;
            }

            $payload['data']['object'] = $current;

            return DB::transaction(function () use ($payload, $id, $process): Response {
                $response = $process($payload);
                if ($response->isSuccessful()) {
                    DB::table('stripe_webhook_receipts')->insert([
                        'event_id' => $payload['id'],
                        'subscription_id' => $id,
                        'event_type' => $payload['type'],
                        'processed_at' => now(),
                    ]);
                }

                return $response;
            });
        });
    }
}
