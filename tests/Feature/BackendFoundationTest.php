<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\Skill;
use App\Models\User;
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

    public function test_mype_can_filter_catalog_and_manage_favorite_freelancers(): void
    {
        Http::fake([
            'https://peruapi.com/api/ruc/20601234567*' => Http::response([
                'ruc' => '20601234567',
                'razon_social' => 'CLIENTE MYPE S.A.C.',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'departamento' => 'LIMA',
                'provincia' => 'LIMA',
                'distrito' => 'MIRAFLORES',
                'mensaje' => 'OK',
                'code' => '200',
            ]),
        ]);

        $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Camila',
            'last_name' => 'Rojas',
            'dni' => '11223344',
            'email' => 'camila.catalog@example.com',
            'password' => 'password123',
        ])->assertCreated();

        $freelancer = User::where('email', 'camila.catalog@example.com')
            ->firstOrFail()
            ->freelancerProfile;

        $freelancer->update([
            'headline' => 'Editora de video para redes',
            'category' => 'Edicion de Video',
            'bio' => 'Edicion de reels y piezas cortas para negocios locales.',
            'suggested_rate' => 'S/ 35',
            'location' => 'Lima',
            'rating' => 4.8,
            'completed_jobs' => 6,
            'availability_status' => 'available',
        ]);

        $skill = Skill::create(['name' => 'Edicion de videos']);
        $freelancer->skills()->attach($skill->id);

        $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Mateo',
            'last_name' => 'Quispe',
            'dni' => '55667788',
            'email' => 'mateo.catalog@example.com',
            'password' => 'password123',
        ])->assertCreated();

        User::where('email', 'mateo.catalog@example.com')
            ->firstOrFail()
            ->freelancerProfile
            ->update([
                'headline' => 'Desarrollador web',
                'category' => 'Desarrollo Web',
                'suggested_rate' => 'S/ 90',
                'location' => 'Arequipa',
            ]);

        $mype = $this->postJson('/api/auth/register/mype', [
            'first_name' => 'Lucia',
            'last_name' => 'Torres',
            'company_name' => 'Cliente MYPE',
            'ruc' => '20601234567',
            'email' => 'lucia.catalog@example.com',
            'password' => 'password123',
        ])->assertCreated();

        $mypeToken = $mype->json('data.access_token');

        $this->withToken($mypeToken)->getJson('/api/catalog?search=video&category=Edicion%20de%20Video&location=Lima&min_rate=30&max_rate=40')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.freelancers.0.id', $freelancer->id)
            ->assertJsonPath('data.freelancers.0.rate_amount', 35);

        $this->withToken($mypeToken)->postJson('/api/favorites', [
            'freelancer_profile_id' => $freelancer->id,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.favorite.id', $freelancer->id);

        $this->withToken($mypeToken)->postJson('/api/favorites', [
            'freelancer_profile_id' => $freelancer->id,
        ])->assertStatus(409);

        $this->withToken($mypeToken)->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('data.favorites.0.id', $freelancer->id)
            ->assertJsonPath('data.favorites.0.availability_status', 'available');

        $this->withToken($mypeToken)->deleteJson("/api/favorites/{$freelancer->id}")
            ->assertOk();

        $this->withToken($mypeToken)->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonCount(0, 'data.favorites');
    }

    public function test_mype_can_explore_published_freelancer_services_with_filters(): void
    {
        Http::fake([
            'https://peruapi.com/api/ruc/20607654321*' => Http::response([
                'ruc' => '20607654321',
                'razon_social' => 'SERVICIOS CLIENTE S.A.C.',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'departamento' => 'LIMA',
                'provincia' => 'LIMA',
                'distrito' => 'SAN ISIDRO',
                'mensaje' => 'OK',
                'code' => '200',
            ]),
        ]);

        $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Diego',
            'last_name' => 'Salazar',
            'dni' => '99887766',
            'email' => 'diego.services@example.com',
            'password' => 'password123',
        ])->assertCreated();

        $freelancer = User::where('email', 'diego.services@example.com')
            ->firstOrFail()
            ->freelancerProfile;

        $freelancer->update([
            'headline' => 'Editor de video para redes',
            'rating' => 4.9,
            'completed_jobs' => 12,
        ]);

        $category = Category::create(['name' => 'Edicion de Video']);
        $otherCategory = Category::create(['name' => 'Desarrollo Web']);

        Service::create([
            'freelancer_profile_id' => $freelancer->id,
            'category_id' => $category->id,
            'title' => 'Edicion de 10 reels para redes sociales',
            'description' => 'Incluye cortes, subtitulos y musica.',
            'price' => 200,
            'currency' => 'PEN',
            'delivery_days' => 5,
            'status' => 'published',
        ]);

        Service::create([
            'freelancer_profile_id' => $freelancer->id,
            'category_id' => $otherCategory->id,
            'title' => 'Landing page para negocio local',
            'description' => 'Pagina simple de aterrizaje.',
            'price' => 700,
            'currency' => 'PEN',
            'delivery_days' => 10,
            'status' => 'draft',
        ]);

        $mype = $this->postJson('/api/auth/register/mype', [
            'first_name' => 'Rosa',
            'last_name' => 'Mendoza',
            'company_name' => 'Servicios Cliente',
            'ruc' => '20607654321',
            'email' => 'rosa.services@example.com',
            'password' => 'password123',
        ])->assertCreated();

        $token = $mype->json('data.access_token');

        $this->withToken($token)->getJson('/api/services?search=reels&category=Video&min_price=150&max_price=250&max_delivery_days=7')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.services.0.title', 'Edicion de 10 reels para redes sociales')
            ->assertJsonPath('data.services.0.price', 200)
            ->assertJsonPath('data.services.0.freelancer.name', 'Diego Salazar')
            ->assertJsonPath('data.services.0.freelancer.rating', '4.90');
    }
}
