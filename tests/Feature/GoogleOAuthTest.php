<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_redirects_to_google_when_credentials_are_configured(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
        ]);
        Socialite::fake('google');

        $this->get(route('google.redirect'))
            ->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_google_login_shows_setup_message_when_credentials_are_missing(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get(route('google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('google_auth_error');
    }

    public function test_verified_google_account_can_create_and_authenticate_a_user(): void
    {
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-user-123',
            'name' => 'Google User',
            'email' => 'google@example.com',
            'user' => ['email_verified' => true],
        ]));

        $response = $this->get(route('google.callback'));
        $user = User::where('email', 'google@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('google-user-123', $user->google_id);
        $this->assertDatabaseHas('teams', ['name' => "Google User's Team"]);
        $this->assertInstanceOf(Team::class, $user->currentTeam);
        $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
    }

    public function test_google_login_rejects_an_unverified_email(): void
    {
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-user-unverified',
            'email' => 'unverified@example.com',
            'user' => ['email_verified' => false],
        ]));

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('google_auth_error');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unverified@example.com']);
    }
}
