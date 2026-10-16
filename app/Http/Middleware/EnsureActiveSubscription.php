<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ((! app()->isProduction() && ! config('billing.enforce_subscriptions'))
            || $request->routeIs('billing.*')
            || $request->user()?->currentTeam?->hasValidSubscription()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'A assinatura da equipe precisa ser regularizada pelo proprietário.'], 402);
        }

        return redirect()->route('billing.required', ['current_team' => $request->user()->currentTeam->slug]);
    }
}
