<?php

namespace App\Actions\Billing;

use App\Models\Team;
use Illuminate\Support\Carbon;

class BuildSubscriptionNotice
{
    /** @return array{status: string, title: string, message: string, tone: string, url: string}|null */
    public function handle(Team $team): ?array
    {
        $subscription = $team->subscription(config('billing.subscription_type'));

        if ($subscription === null) {
            return null;
        }

        $status = $subscription->stripe_status;
        $tone = 'neutral';

        if (in_array($status, ['past_due', 'unpaid', 'incomplete'], true)) {
            $title = 'Pagamento pendente';
            $message = 'Seu pagamento ainda não foi confirmado. Confira a forma de pagamento e a situação da assinatura.';
            $tone = 'warning';
        } elseif ($subscription->ends_at?->isFuture()) {
            if ($subscription->ends_at->greaterThan(now()->addDays(5))) {
                return null;
            }

            $title = 'Cancelamento agendado';
            $message = 'Sua assinatura termina em '.$subscription->ends_at->format('d/m/Y').'. Não haverá renovação automática.';
            $tone = 'warning';
        } elseif (in_array($status, ['canceled', 'incomplete_expired'], true) || $subscription->ends_at?->isPast()) {
            $title = 'Assinatura encerrada';
            $message = 'Confira as opções de contratação para continuar com o Pedix.';
            $tone = 'warning';
        } elseif ($status === 'trialing') {
            if ($subscription->trial_ends_at?->isFuture()) {
                if ($subscription->trial_ends_at->greaterThan(now()->addDays(5))) {
                    return null;
                }

                $days = (int) ceil(now()->diffInDays($subscription->trial_ends_at));
                $title = 'Seu período grátis termina em '.$days.($days === 1 ? ' dia' : ' dias');
                $message = 'O teste termina em '.$subscription->trial_ends_at->format('d/m/Y').'. Após essa data, começa a cobrança automática do plano, se não houver cancelamento.';
                $tone = $days <= 3 ? 'warning' : 'neutral';
            } else {
                $title = 'Período grátis em atualização';
                $message = 'Estamos aguardando a confirmação da Stripe sobre o término do teste e a próxima cobrança.';
            }
        } elseif ($status === 'active') {
            $title = 'Assinatura ativa';
            $periodEnd = $subscription->getAttribute('current_period_ends_at');
            $date = is_string($periodEnd) ? Carbon::parse($periodEnd) : null;
            if ($date === null || $date->isPast() || $date->greaterThan(now()->addDays(5))) {
                return null;
            }

            $message = 'Próxima cobrança em '.$date->format('d/m/Y').'. A renovação do seu plano é automática.';
        } else {
            $title = 'Confira sua assinatura';
            $message = 'A assinatura precisa de atenção. Confira os detalhes antes da próxima cobrança.';
            $tone = 'warning';
        }

        return [
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'tone' => $tone,
            'url' => route('billing.index', ['current_team' => $team->slug]),
        ];
    }
}
