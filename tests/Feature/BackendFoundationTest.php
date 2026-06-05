<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $register = $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Camila',
            'last_name' => 'Rojas',
            'dni' => '12345678',
            'email' => 'camila@example.com',
            'password' => 'password123',
        ]);

        $register->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.user_type', 'freelancer')
            ->assertJsonPath('data.user.account_type', 'freelancer')
            ->assertJsonPath('data.freelancer_profile.dni', '12345678');

        $token = $register->json('data.access_token');

        $this->withToken($token)->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('data.module', 'users');

        $this->withToken($token)->getJson('/api/profiles')
            ->assertOk()
            ->assertJsonPath('data.module', 'profiles');

        $this->postJson('/api/auth/login', [
            'email' => 'camila@example.com',
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
            'email' => 'camila@example.com',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dni']);
    }

    public function test_register_mype_rejects_ruc_with_letters(): void
    {
        $this->postJson('/api/auth/register/mype', [
            'first_name' => 'Lucia',
            'last_name' => 'Torres',
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
            'first_name' => 'Lucia',
            'last_name' => 'Torres',
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

    public function test_gemini_analysis_sends_full_freelancer_payload_and_updates_profile(): void
    {
        config(['services.gemini.key' => 'fake-gemini-key']);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'headline' => 'Editor de video para marcas',
                                        'category' => 'Edicion de Video',
                                        'suggested_rate' => 'S/ 35',
                                        'bio' => 'Creo videos claros para marcas que necesitan vender mejor en canales digitales.',
                                        'profile_criteria' => [
                                            'positioning' => 'Especialista en video corto para MYPEs.',
                                            'target_clients' => ['MYPEs', 'Emprendedores digitales'],
                                            'service_keywords' => ['video corto', 'edicion para redes'],
                                            'portfolio_focus' => ['reels promocionales'],
                                            'pricing_notes' => 'La tarifa considera herramientas y experiencia inicial.',
                                        ],
                                        'suggested_projects' => [
                                            [
                                                'title' => 'Video promocional para negocio local',
                                                'description' => 'Pieza breve para redes sociales.',
                                                'estimated_time' => '12 horas',
                                                'tasks' => ['Guion', 'Edicion', 'Exportacion'],
                                            ],
                                        ],
                                        'tips' => ['Agrega muestras antes y despues.'],
                                        'strengths' => ['Edicion con enfoque comercial'],
                                        'availability_summary' => 'Puede invertir 10 horas por semana.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $register = $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Camila',
            'last_name' => 'Rojas',
            'dni' => '87654321',
            'email' => 'camila.gemini@example.com',
            'password' => 'password123',
        ]);

        $token = $register->json('data.access_token');

        $this->withToken($token)->postJson('/api/gemini/analyze', [
            'skills' => ['Edicion de videos', 'Diseno de branding'],
            'tools' => ['Photoshop', 'CapCut'],
            'description' => 'Me gusta crear piezas visuales para negocios que quieren crecer en redes.',
            'areas' => ['Comunicacion'],
            'certificates' => ['Curso de Excel - Avanzado'],
            'has_project_experience' => 'si',
            'projects' => [
                [
                    'name' => 'Video para cafeteria',
                    'description' => 'Video corto para promocionar un producto.',
                    'time' => '15 horas',
                ],
            ],
            'availability' => 'si',
            'availability_time' => '10 horas por semana',
            'freelance_goals' => 'Conseguir clientes MYPE.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.headline', 'Editor de video para marcas')
            ->assertJsonPath('data.profile_criteria.service_keywords.0', 'video corto')
            ->assertJsonPath('data.suggested_projects.0.tasks.1', 'Edicion');

        $this->assertDatabaseHas('freelancer_profiles', [
            'dni' => '87654321',
            'headline' => 'Editor de video para marcas',
            'category' => 'Edicion de Video',
            'suggested_rate' => 'S/ 35',
            'availability_status' => 'available',
        ]);

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return str_contains($body, '"responseMimeType":"application\/json"')
                && str_contains($body, 'No hagas inferencias')
                && str_contains($body, '10 horas por semana')
                && str_contains($body, 'Video para cafeteria');
        });
    }
}
