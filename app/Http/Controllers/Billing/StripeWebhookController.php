<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ProcessStripeWebhook;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class StripeWebhookController extends WebhookController
{
    public function __construct(private readonly ProcessStripeWebhook $processWebhook)
    {
        parent::__construct();
    }

    public function handleWebhook(Request $request): Response
    {
        $payload = $request->json()->all();
        abort_unless(is_string($payload['id'] ?? null) && is_string($payload['type'] ?? null), 400);

        try {
            return $this->processWebhook->handle($payload, function (array $current): Response {
                $normalized = Request::create('/stripe/webhook', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($current, JSON_THROW_ON_ERROR));

                return parent::handleWebhook($normalized);
            });
        } catch (ApiErrorException|LockTimeoutException|UnexpectedValueException $exception) {
            report($exception);

            return response()->json(['message' => 'Não foi possível sincronizar a assinatura. Tente novamente.'], 503);
        }
    }

    /** @param array<string, mixed> $payload */
    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        return $this->handleCustomerSubscriptionUpdated($payload);
    }

    /** @param array<string, mixed> $payload */
    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        return $this->handleCustomerSubscriptionUpdated($payload);
    }

    /** @param array<string, mixed> $payload */
    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        if (($payload['data']['object']['status'] ?? null) === 'incomplete_expired') {
            parent::handleCustomerSubscriptionUpdated($payload);

            return response('Webhook handled', 200);
        }

        return parent::handleCustomerSubscriptionUpdated($payload);
    }
}
