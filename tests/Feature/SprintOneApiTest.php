<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintOneApiTest extends TestCase
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

    public function test_freelancer_auth_and_profile_flow(): void
    {
        $register = $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Camila',
            'last_name' => 'Rojas',
            'email' => 'camila@example.com',
            'password' => 'password123',
        ]);

        $register->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.account_type', 'freelancer');

        $token = $register->json('data.access_token');

        $this->withToken($token)->postJson('/api/profile', [
            'headline' => 'Disenadora grafica',
            'category' => 'diseno',
            'location' => 'Lima, PE',
            'hourly_rate' => 90,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.headline', 'Disenadora grafica');

        $this->withToken($token)->patchJson('/api/profile/skills', [
            'skills' => ['Branding', 'Logos', 'Social Media'],
        ])->assertOk()
            ->assertJsonPath('data.skills.0', 'Branding');

        $this->withToken($token)->patchJson('/api/profile/description', [
            'description' => 'Creo marcas visuales claras para negocios digitales.',
        ])->assertOk()
            ->assertJsonPath('data.description', 'Creo marcas visuales claras para negocios digitales.');

        $this->withToken($token)->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)->getJson('/api/profile')
            ->assertUnauthorized();
    }
}
