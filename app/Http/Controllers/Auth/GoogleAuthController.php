<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(private CreateTeam $createTeam) {}

    public function redirectToGoogle(): RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->with('google_auth_error', 'O acesso com Google ainda não foi configurado neste ambiente.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        if (request()->has('error')) {
            return redirect()->route('login')->with('google_auth_error', 'O acesso com Google foi cancelado. Tente novamente.');
        }

        $googleUser = Socialite::driver('google')->user();
        $email = $googleUser->getEmail();
        $googleId = $googleUser->getId();
        $isEmailVerified = $googleUser->user['email_verified']
            ?? $googleUser->user['verified_email']
            ?? false;

        if (! $email || ! $googleId || $isEmailVerified !== true) {
            return redirect()->route('login')->with('google_auth_error', 'O Google não confirmou o e-mail desta conta.');
        }

        $user = DB::transaction(function () use ($email, $googleId, $googleUser): ?User {
            $user = User::query()->where('google_id', $googleId)->first()
                ?? User::query()->where('email', $email)->first();

            if ($user && $user->google_id && $user->google_id !== $googleId) {
                return null;
            }

            if (! $user) {
                $user = User::create([
                    'name' => $googleUser->getName() ?: Str::before($email, '@'),
                    'email' => $email,
                    'password' => Str::random(64),
                ]);
            }

            $user->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if (! $user->currentTeam && ! $user->personalTeam()) {
                $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);
            } elseif (! $user->currentTeam && $user->personalTeam()) {
                $user->switchTeam($user->personalTeam());
            }

            return $user;
        });

        if (! $user) {
            return redirect()->route('login')->with('google_auth_error', 'Esta conta já está vinculada a outro acesso Google.');
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();
        URL::defaults(['current_team' => $user->currentTeam->slug]);

        return redirect()->intended(route('dashboard', ['current_team' => $user->currentTeam->slug]));
    }
}
