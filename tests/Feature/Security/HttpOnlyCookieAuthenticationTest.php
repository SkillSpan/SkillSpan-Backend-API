<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HttpOnlyCookieAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'https://frontend.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth_cookie.expose_token' => false,
            'auth_cookie.secure' => false,
            'auth_cookie.same_site' => 'lax',
            'cors.allowed_origins' => [self::ORIGIN],
        ]);

        $role = Role::create(['name' => 'Learner', 'slug' => 'learner', 'description' => '']);
        $user = User::forceCreate([
            'name' => 'Cookie Learner',
            'email' => 'cookie@test.com',
            'password' => 'password123',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role->id);
    }

    public function test_login_uses_an_http_only_cookie_without_exposing_the_token_in_json(): void
    {
        $response = $this->login();

        $response->assertOk()
            ->assertJsonMissingPath('data.token')
            ->assertJsonPath('data.auth_mode', 'http_only_cookie');

        $cookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === config('auth_cookie.name'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertNotSame('', $cookie->getValue());
    }

    public function test_cookie_authentication_accepts_trusted_ajax_mutations_and_rejects_csrf(): void
    {
        $cookie = $this->authCookie();

        $this->cookieLogout($cookie)
            ->assertStatus(419)
            ->assertJsonPath('code', 'CSRF_ORIGIN_MISMATCH');

        $this->cookieLogout($cookie, 'https://evil.test')
            ->assertStatus(419);

        $this->cookieLogout($cookie, self::ORIGIN)
            ->assertOk();
    }

    public function test_bearer_clients_remain_compatible_without_browser_csrf_headers(): void
    {
        config(['auth_cookie.expose_token' => true]);
        $token = $this->login()->json('data.token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();
    }

    private function login()
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'cookie@test.com',
            'password' => 'password123',
        ]);
    }

    private function authCookie(): string
    {
        $response = $this->login();
        $cookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === config('auth_cookie.name'));

        return $cookie->getValue();
    }

    private function cookieLogout(string $cookie, ?string $origin = null)
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if ($origin !== null) {
            $server['HTTP_ORIGIN'] = $origin;
            $server['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        }

        return $this->call(
            'POST',
            '/api/v1/auth/logout',
            [],
            [config('auth_cookie.name') => $cookie],
            [],
            $server,
        );
    }
}
