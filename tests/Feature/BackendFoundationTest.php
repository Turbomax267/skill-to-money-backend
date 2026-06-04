<?php

namespace Tests\Feature;

use App\Mail\WelcomeAccountMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BackendFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoints_return_ok(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_register_login_module_status_and_logout_flow(): void
    {
        Mail::fake();

        $register = $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Camila',
            'last_name' => 'Rojas',
            'dni' => '12345678',
            'email' => 'camila@gmail.com',
            'password' => 'password123',
        ]);

        $register->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.user_type', 'freelancer')
            ->assertJsonPath('data.user.account_type', 'freelancer');

        Mail::assertSent(WelcomeAccountMail::class);

        $token = $register->json('data.access_token');

        $this->withToken($token)->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('data.module', 'users');

        $this->withToken($token)->getJson('/api/profiles')
            ->assertOk()
            ->assertJsonPath('data.module', 'profiles');

        $this->postJson('/api/auth/login', [
            'email' => 'camila@gmail.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.user_type', 'freelancer');

        $this->withToken($token)->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)->getJson('/api/users')
            ->assertUnauthorized();
    }
}
