<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardSubscriptionNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_trial_countdown_and_management_link(): void
    {
        $this->freezeTime();
        $owner = $this->ownerWithSubscription('trialing', ['trial_ends_at' => now()->addDays(3)]);

        $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('subscriptionNotice.title', 'Seu período grátis termina em 3 dias')
            ->where('subscriptionNotice.tone', 'warning')
            ->where('subscriptionNotice.url', route('billing.index', $owner->currentTeam->slug))
            ->where('subscriptionNotice.message', fn (string $message): bool => str_contains($message, 'cobrança automática')));
    }

    public function test_active_subscription_displays_the_synced_renewal_date(): void
    {
        $this->freezeTime();
        $date = now()->addDays(5);
        $owner = $this->ownerWithSubscription('active', ['current_period_ends_at' => $date]);

        $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('subscriptionNotice.title', 'Assinatura ativa')
            ->where('subscriptionNotice.message', 'Próxima cobrança em '.$date->format('d/m/Y').'. A renovação do seu plano é automática.'));
    }

    public function test_active_subscription_without_a_synced_date_does_not_invent_one(): void
    {
        $owner = $this->ownerWithSubscription('active');

        $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('subscriptionNotice', null));
    }

    public function test_scheduled_cancellation_takes_precedence_over_the_trial(): void
    {
        $this->freezeTime();
        $end = now()->addDays(2);
        $owner = $this->ownerWithSubscription('trialing', ['trial_ends_at' => now()->addDays(5), 'ends_at' => $end]);

        $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('subscriptionNotice.title', 'Cancelamento agendado')
            ->where('subscriptionNotice.message', 'Sua assinatura termina em '.$end->format('d/m/Y').'. Não haverá renovação automática.'));
    }

    public function test_payment_failure_shows_an_actionable_warning(): void
    {
        $owner = $this->ownerWithSubscription('past_due');

        $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('subscriptionNotice.title', 'Pagamento pendente')
            ->where('subscriptionNotice.tone', 'warning'));
    }

    public function test_ended_subscription_does_not_display_automatic_renewal(): void
    {
        $owner = $this->ownerWithSubscription('canceled', ['ends_at' => now()->subDay()]);

        $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('subscriptionNotice.title', 'Assinatura encerrada'));
    }

    public function test_expired_trial_waiting_for_webhook_does_not_claim_it_is_still_free(): void
    {
        $owner = $this->ownerWithSubscription('trialing', ['trial_ends_at' => now()->subHour()]);

        $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('subscriptionNotice.title', 'Período grátis em atualização'));
    }

    public function test_owner_without_subscription_sees_no_notice(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('subscriptionNotice', null));
    }

    public function test_admin_and_member_do_not_receive_billing_information(): void
    {
        $owner = $this->ownerWithSubscription('active', ['current_period_ends_at' => now()->addDay()]);
        foreach ([TeamRole::Admin, TeamRole::Member] as $role) {
            $member = User::factory()->create();
            $owner->currentTeam->members()->attach($member, ['role' => $role->value]);
            $member->forceFill(['current_team_id' => $owner->current_team_id])->save();

            $this->actingAs($member)->get(route('dashboard', $owner->currentTeam->slug))
                ->assertOk()->assertInertia(fn (Assert $page) => $page->where('subscriptionNotice', null));
        }
    }

    public function test_subscription_from_another_team_is_not_shown(): void
    {
        $this->ownerWithSubscription('past_due');
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('subscriptionNotice', null));
    }

    public function test_notices_are_hidden_more_than_five_days_before_the_relevant_date(): void
    {
        $this->freezeTime();
        foreach ([
            ['trialing', ['trial_ends_at' => now()->addDays(5)->addSecond()]],
            ['active', ['current_period_ends_at' => now()->addDays(5)->addSecond()]],
            ['active', ['ends_at' => now()->addDays(5)->addSecond(), 'current_period_ends_at' => now()->addDay()]],
            ['active', ['current_period_ends_at' => now()->subHour()]],
        ] as [$status, $attributes]) {
            $owner = $this->ownerWithSubscription($status, $attributes);
            $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
                ->assertOk()->assertInertia(fn (Assert $page) => $page->where('subscriptionNotice', null));
        }
    }

    public function test_trial_and_cancellation_notices_start_exactly_five_days_before(): void
    {
        $this->freezeTime();
        foreach ([
            ['trialing', ['trial_ends_at' => now()->addDays(5)], 'Seu período grátis termina em 5 dias'],
            ['active', ['ends_at' => now()->addDays(5)], 'Cancelamento agendado'],
        ] as [$status, $attributes, $title]) {
            $owner = $this->ownerWithSubscription($status, $attributes);
            $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
                ->assertOk()->assertInertia(fn (Assert $page) => $page->where('subscriptionNotice.title', $title));
        }
    }

    /** @param array<string, mixed> $attributes */
    private function ownerWithSubscription(string $status, array $attributes = []): User
    {
        $owner = User::factory()->create();
        $owner->currentTeam->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_'.$owner->id,
            'stripe_status' => $status,
            ...$attributes,
        ]);

        return $owner;
    }
}
