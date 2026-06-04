<?php

namespace Tests\Feature;

use App\Mail\WelcomeAccountMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
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
            ->assertJsonPath('data.user.account_type', 'freelancer')
            ->assertJsonPath('data.freelancer_profile.dni', '12345678');

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

    public function test_register_rejects_invalid_name_last_name_and_email(): void
    {
        $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Camila123',
            'last_name' => 'Rojas!',
            'dni' => '12345678',
            'email' => 'camila.example.com',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email']);
    }

    public function test_register_freelancer_rejects_dni_with_letters(): void
    {
        $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Camila',
            'last_name' => 'Rojas',
            'dni' => '12ABC678',
            'email' => 'camila@gmail.com',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dni']);
    }

    public function test_register_mype_rejects_ruc_with_letters(): void
    {
        $this->postJson('/api/auth/register/mype', [
            'company_name' => 'Empresa Demo SAC',
            'ruc' => '20ABC123456',
            'email' => 'lucia@example.com',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ruc']);
    }

    public function test_register_mype_uses_peru_api_business_name(): void
    {
        config(['services.peru_api.key' => 'fake-key']);

        Http::fake([
            'https://peruapi.com/api/ruc/20601234567*' => Http::response([
                'ruc' => '20601234567',
                'razon_social' => 'SKILL TO MONEY S.A.C.',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'departamento' => 'LIMA',
                'provincia' => 'LIMA',
                'distrito' => 'MIRAFLORES',
                'mensaje' => 'OK',
                'code' => '200',
            ]),
        ]);

        $this->postJson('/api/auth/register/mype', [
            'company_name' => 'Nombre editable ignorado',
            'ruc' => '20601234567',
            'email' => 'lucia@example.com',
            'password' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.user_type', 'mype')
            ->assertJsonPath('data.mype_profile.business_name', 'SKILL TO MONEY S.A.C.')
            ->assertJsonPath('data.mype_profile.ruc', '20601234567');

        $this->assertDatabaseHas('mype_profiles', [
            'business_name' => 'SKILL TO MONEY S.A.C.',
            'ruc' => '20601234567',
        ]);
    }
}
