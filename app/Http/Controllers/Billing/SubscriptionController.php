<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CreateSubscriptionCheckout;
use App\Contracts\SubscriptionGateway;
use App\Enums\BillingPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StartSubscriptionRequest;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function checkout(StartSubscriptionRequest $request, CreateSubscriptionCheckout $createCheckout): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403);

        try {
            return Inertia::location($createCheckout->handle($team, BillingPlan::from($request->validated('plan'))));
        } catch (ApiErrorException $exception) {
            report($exception);

            throw ValidationException::withMessages(['billing' => 'Não foi possível iniciar o checkout agora. Tente novamente em instantes.']);
        }
    }

    public function portal(Request $request, SubscriptionGateway $gateway): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403);

        if ($team->stripe_id === null) {
            throw ValidationException::withMessages(['billing' => 'Esta equipe ainda não tem um cadastro de cobrança.']);
        }

        try {
            return Inertia::location($gateway->billingPortal($team));
        } catch (ApiErrorException $exception) {
            report($exception);

            throw ValidationException::withMessages(['billing' => 'Não foi possível abrir o gerenciamento da assinatura. Tente novamente em instantes.']);
        }
    }
}
