<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    public function test_session_configuration_uses_browser_security_controls(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertSame('json', config('session.serialization'));
        $this->assertSame('/', config('session.path'));
    }

    public function test_production_cookie_flags_are_sent_to_the_browser(): void
    {
        config([
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        $response = $this->withSession(['cookie_security_check' => true])->get(route('login'));
        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', $sessionCookie->getSameSite());
        $this->assertSame('/', $sessionCookie->getPath());
    }
}
