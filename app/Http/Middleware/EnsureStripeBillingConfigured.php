<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStripeBillingConfigured
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            filled(config('cashier.key')) && filled(config('cashier.secret')) && filled(config('cashier.webhook.secret')),
            503,
            'A cobrança de assinaturas ainda não está configurada.',
        );

        return $next($request);
    }
}
