<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->has('passwordRules'),
        );
    }

    public function test_registration_screen_includes_team_invitation_context()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Laravel Team']);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this->get(route('register', ['invitation' => $invitation->code]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->where('teamInvitation.code', $invitation->code)
            ->where('teamInvitation.teamName', 'Laravel Team'),
        );
    }

    public function test_new_users_can_register()
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'delivery_name' => 'Fuks Lanches',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->first();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('Fuks Lanches', $user->currentTeam->name);
        $this->assertSame('fuks-lanches', $user->currentTeam->slug);
        $this->assertTrue($user->currentTeam->is_personal);
        $this->assertSame(TeamRole::Owner, $user->teamRole($user->currentTeam));

        Notification::assertSentTo($user, VerifyEmail::class);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_local_registration_verifies_the_user_without_sending_a_verification_email(): void
    {
        config(['auth.require_email_verification' => false]);
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Local User',
            'delivery_name' => 'Delivery Local',
            'email' => 'local@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'local@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        Notification::assertNothingSent();
        $response->assertRedirect(route('dashboard'));
        $this->get(route('dashboard', $user->currentTeam->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentTeam.name', 'Delivery Local')
                ->where('auth.user.name', 'Local User'));
    }

    public function test_delivery_name_is_required_and_uses_existing_team_name_rules(): void
    {
        foreach ([null, '', '   ', str_repeat('a', 256), ['invalid'], 'admin'] as $name) {
            $this->post(route('register.store'), [
                'name' => 'Test User', 'delivery_name' => $name,
                'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            ])->assertSessionHasErrors('delivery_name');
            $this->assertGuest();
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('teams', 0);
        }
        $this->post(route('register.store'), [
            'name' => 'Test User', 'email' => 'test@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('delivery_name');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_deliveries_with_the_same_name_have_separate_teams_and_unique_addresses(): void
    {
        $action = $this->app->make(CreateNewUser::class);
        $first = $action->create([
            'name' => 'Ana', 'delivery_name' => 'Sabor da Casa', 'email' => 'ana@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ]);
        $second = $action->create([
            'name' => 'João', 'delivery_name' => 'Sabor da Casa', 'email' => 'joao@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ]);

        $this->assertSame('Sabor da Casa', $first->currentTeam->name);
        $this->assertSame('Sabor da Casa', $second->currentTeam->name);
        $this->assertNotSame($first->current_team_id, $second->current_team_id);
        $this->assertNotSame($first->currentTeam->slug, $second->currentTeam->slug);
        $this->assertFalse($first->belongsToTeam($second->currentTeam));
    }

    public function test_registration_rejects_an_email_that_is_already_registered(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('register.store'), [
            'name' => 'Another User',
            'delivery_name' => 'Outro Delivery',
            'email' => 'existing@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => 'Este e-mail já está em uso.']);
        $this->assertGuest();
    }
}
