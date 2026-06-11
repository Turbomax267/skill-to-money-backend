<?php

namespace Tests\Feature;

use App\Mail\WelcomeAccountMail;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Service;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            'email' => 'lucia@gmail.com',
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
            'email' => 'lucia.ruc@gmail.com',
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

    public function test_gemini_analysis_sends_compact_safe_payload_and_updates_profile(): void
    {
        config([
            'services.gemini.key' => 'fake-gemini-key',
            'services.openrouter.key' => null,
            'services.groq.key' => null,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'titulo_profesional' => 'Editor de video para marcas',
                                        'descripcion_profesional' => 'Creo videos claros para marcas que necesitan vender mejor en canales digitales.',
                                        'propuesta_valor' => 'Ayudo a MYPEs a comunicar mejor sus productos con videos cortos y claros.',
                                        'skills_destacadas' => ['video corto', 'edicion para redes'],
                                        'herramientas_destacadas' => ['CapCut', 'Photoshop'],
                                        'proyectos_optimizados' => [
                                            [
                                                'nombre' => 'Video promocional para negocio local',
                                                'descripcion_mejorada' => 'Pieza breve para redes sociales orientada a presentar un producto.',
                                                'categoria' => 'Edicion de Video',
                                                'herramientas' => ['CapCut'],
                                            ],
                                        ],
                                        'servicios_recomendados' => [
                                            [
                                                'nombre' => 'Edicion de reels para redes',
                                                'descripcion' => 'Edicion de videos cortos con cortes limpios y subtitulos.',
                                                'precio_sugerido' => 'S/ 35 por hora',
                                                'tiempo_entrega' => '2 a 3 dias',
                                                'categoria' => 'Edicion de Video',
                                            ],
                                        ],
                                        'recomendaciones_mejora' => ['Agrega muestras antes y despues.'],
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
            'email' => 'camila.gemini@gmail.com',
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
                    'tools' => ['CapCut'],
                    'category' => 'Edicion de Video',
                ],
            ],
            'availability' => 'si',
            'availability_time' => '10 horas por semana',
            'freelance_goals' => 'Conseguir clientes MYPE.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.titulo_profesional', 'Editor de video para marcas')
            ->assertJsonPath('data.servicios_recomendados.0.precio_sugerido', 'S/ 35 por hora')
            ->assertJsonPath('data.headline', 'Editor de video para marcas')
            ->assertJsonPath('data.profile_criteria.service_keywords.0', 'video corto')
            ->assertJsonPath('data.suggested_projects.0.title', 'Video promocional para negocio local');

        $this->assertDatabaseHas('freelancer_profiles', [
            'dni' => '87654321',
            'headline' => 'Editor de video para marcas',
            'category' => 'Edicion de Video',
            'experience_area' => 'Edicion de Video',
            'suggested_rate' => 'S/ 35 por hora',
            'availability_status' => 'available',
        ]);

        $this->assertDatabaseHas('skills', ['name' => 'video corto']);
        $this->assertDatabaseHas('skills', ['name' => 'Photoshop']);
        $this->assertDatabaseHas('services', [
            'title' => 'Edicion de reels para redes',
            'price' => 35,
            'currency' => 'PEN',
            'delivery_days' => 3,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('portfolio_projects', [
            'title' => 'Video promocional para negocio local',
            'is_featured' => true,
        ]);

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return str_contains($body, '"responseMimeType":"application\/json"')
                && str_contains($body, '"maxOutputTokens":1000')
                && str_contains($body, 'Eres un asesor experto en freelancing')
                && str_contains($body, 'No inventes experiencia')
                && str_contains($body, '10 horas por semana')
                && str_contains($body, 'Video para cafeteria')
                && !str_contains($body, '87654321')
                && !str_contains($body, 'Camila')
                && !str_contains($body, 'Rojas')
                && !str_contains($body, 'password123')
                && !str_contains($body, 'linkedin')
                && !str_contains($body, 'instagram');
        });
    }

    public function test_gemini_analysis_uses_openrouter_backup_when_gemini_rate_limits(): void
    {
        config([
            'services.gemini.key' => 'fake-gemini-key',
            'services.openrouter.key' => 'fake-openrouter-key',
            'services.openrouter.model' => 'google/gemini-2.5-flash-lite',
            'services.groq.key' => null,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'Quota exceeded',
                ],
            ], 429),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'titulo_profesional' => 'Diseñadora de branding para negocios locales',
                                'descripcion_profesional' => 'Creo piezas visuales claras para negocios que empiezan a vender en redes.',
                                'propuesta_valor' => 'Ayudo a ordenar la imagen de negocios locales con diseños simples y consistentes.',
                                'skills_destacadas' => ['branding', 'diseño para redes'],
                                'herramientas_destacadas' => ['Canva', 'Photoshop'],
                                'proyectos_optimizados' => [
                                    [
                                        'nombre' => 'Identidad visual para tienda',
                                        'descripcion_mejorada' => 'Paquete visual basico para presentar mejor una tienda local.',
                                        'categoria' => 'Diseño de branding',
                                        'herramientas' => ['Canva'],
                                    ],
                                ],
                                'servicios_recomendados' => [
                                    [
                                        'nombre' => 'Kit visual para redes',
                                        'descripcion' => 'Diseño de piezas base para publicar productos o promociones.',
                                        'precio_sugerido' => 'S/ 150 por proyecto',
                                        'tiempo_entrega' => '5 dias',
                                        'categoria' => 'Diseño de branding',
                                    ],
                                ],
                                'recomendaciones_mejora' => ['Incluye ejemplos antes y despues.'],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $register = $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'dni' => '45678912',
            'email' => 'ana.openrouter@gmail.com',
            'password' => 'password123',
        ]);

        $this->withToken($register->json('data.access_token'))->postJson('/api/gemini/analyze', [
            'skills' => ['Branding'],
            'tools' => ['Canva', 'Photoshop'],
            'description' => 'Quiero crear piezas visuales para negocios locales que venden en redes.',
            'projects' => [
                [
                    'name' => 'Identidad visual para tienda',
                    'description' => 'Propuesta de logo y colores para una tienda local.',
                    'tools' => ['Canva'],
                    'category' => 'Diseño de branding',
                ],
            ],
            'availability' => 'si',
            'availability_time' => '12 horas por semana',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Gemini no estuvo disponible. Perfil generado con OpenRouter.')
            ->assertJsonPath('data.titulo_profesional', 'Diseñadora de branding para negocios locales')
            ->assertJsonPath('data.servicios_recomendados.0.precio_sugerido', 'S/ 150 por proyecto');

        $profile = User::where('email', 'ana.openrouter@gmail.com')->firstOrFail()->freelancerProfile;

        $this->assertSame('openrouter', $profile->gemini_analysis['source'] ?? null);
        $this->assertDatabaseHas('services', [
            'title' => 'Kit visual para redes',
            'price' => 150,
            'delivery_days' => 5,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('portfolio_projects', [
            'title' => 'Identidad visual para tienda',
        ]);
        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return str_contains($request->url(), 'openrouter.ai')
                && ($body['max_tokens'] ?? null) === 1000
                && ($body['model'] ?? null) === 'google/gemini-2.5-flash-lite'
                && ($body['messages'][0]['content'] ?? null) === 'Responde únicamente en JSON válido.';
        });
    }

    public function test_gemini_analysis_returns_error_when_all_ai_providers_fail(): void
    {
        config([
            'services.gemini.key' => 'fake-gemini-key',
            'services.openrouter.key' => null,
            'services.groq.key' => null,
            'services.ai.local_fallback_enabled' => false,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'Quota exceeded',
                ],
            ], 429),
        ]);

        $register = $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Lucia',
            'last_name' => 'Perez',
            'dni' => '12349876',
            'email' => 'lucia.gemini429@gmail.com',
            'password' => 'password123',
        ]);

        $this->withToken($register->json('data.access_token'))->postJson('/api/gemini/analyze', [
            'skills' => ['Diseno de branding'],
            'tools' => ['Canva'],
            'description' => 'Quiero crear piezas visuales simples para negocios locales.',
            'projects' => [
                [
                    'name' => 'Identidad visual para tienda',
                    'description' => 'Propuesta de logo y piezas base para una tienda local.',
                    'tools' => ['Canva'],
                    'category' => 'Diseno de branding',
                ],
            ],
            'availability' => 'si',
            'availability_time' => '8 horas por semana',
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No se pudo generar el perfil con Skill Bot. Gemini/OpenRouter/Groq no respondieron correctamente. Revisa cuota o API keys e intenta otra vez.');

        $profile = User::where('email', 'lucia.gemini429@gmail.com')->firstOrFail()->freelancerProfile;

        $this->assertNull($profile->gemini_analysis);
        $this->assertDatabaseMissing('services', ['title' => 'Servicio inicial de Diseno de branding']);
        $this->assertDatabaseMissing('portfolio_projects', ['title' => 'Identidad visual para tienda']);

        Http::assertSentCount(1);
    }

    public function test_mype_can_filter_catalog_and_manage_favorite_freelancers(): void
    {
        config(['services.peru_api.key' => 'fake-key']);

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
            'email' => 'camila.catalog@gmail.com',
            'password' => 'password123',
        ])->assertCreated();

        $freelancer = User::where('email', 'camila.catalog@gmail.com')
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
            'email' => 'mateo.catalog@gmail.com',
            'password' => 'password123',
        ])->assertCreated();

        User::where('email', 'mateo.catalog@gmail.com')
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
            'email' => 'lucia.catalog@gmail.com',
            'password' => 'password123',
        ])->assertCreated();

        $mypeToken = $mype->json('data.access_token');

        $this->withToken($mypeToken)->getJson('/api/catalog?search=video&category=Edicion%20de%20Video&location=Lima&min_rate=30&max_rate=40&min_rating=4')
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

    public function test_mype_receives_recommended_freelancers_by_need(): void
    {
        config(['services.peru_api.key' => 'fake-key']);

        Http::fake([
            'https://peruapi.com/api/ruc/20603334445*' => Http::response([
                'ruc' => '20603334445',
                'razon_social' => 'RECOMENDACIONES MYPE S.A.C.',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'departamento' => 'LIMA',
                'provincia' => 'LIMA',
                'distrito' => 'SURCO',
                'mensaje' => 'OK',
                'code' => '200',
            ]),
        ]);

        $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Valeria',
            'last_name' => 'Lopez',
            'dni' => '44112233',
            'email' => 'valeria.reco@gmail.com',
            'password' => 'password123',
        ])->assertCreated();

        $recommended = User::where('email', 'valeria.reco@gmail.com')
            ->firstOrFail()
            ->freelancerProfile;

        $recommended->update([
            'headline' => 'Especialista en reels para MYPEs',
            'category' => 'Edicion de Video',
            'bio' => 'Creo reels promocionales para restaurantes y negocios locales.',
            'suggested_rate' => 'S/ 45',
            'rating' => 4.9,
            'completed_jobs' => 15,
            'availability_status' => 'available',
        ]);

        $skill = Skill::create(['name' => 'Edicion de videos']);
        $recommended->skills()->attach($skill->id);

        $this->postJson('/api/auth/register/freelancer', [
            'first_name' => 'Bruno',
            'last_name' => 'Diaz',
            'dni' => '77441122',
            'email' => 'bruno.reco@gmail.com',
            'password' => 'password123',
        ])->assertCreated();

        User::where('email', 'bruno.reco@gmail.com')
            ->firstOrFail()
            ->freelancerProfile
            ->update([
                'headline' => 'Desarrollador backend',
                'category' => 'Desarrollo Web',
                'suggested_rate' => 'S/ 120',
                'rating' => 4.2,
            ]);

        $mype = $this->postJson('/api/auth/register/mype', [
            'company_name' => 'Recomendaciones MYPE',
            'ruc' => '20603334445',
            'email' => 'recomendaciones.mype@gmail.com',
            'password' => 'password123',
        ])->assertCreated();

        $this->withToken($mype->json('data.access_token'))->getJson('/api/recommendations?type=freelancer&search=reels%20restaurante&category=Edicion%20de%20Video&skill=videos&max_rate=60&min_rating=4.5')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recommendations.0.id', $recommended->id)
            ->assertJsonPath('data.recommendations.0.score', 100)
            ->assertJsonPath('data.recommendations.0.reasons.0', 'Coincide con la categoria solicitada.');
    }

    public function test_mype_can_explore_published_freelancer_services_with_filters(): void
    {
        config(['services.peru_api.key' => 'fake-key']);

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
            'email' => 'diego.services@gmail.com',
            'password' => 'password123',
        ])->assertCreated();

        $freelancer = User::where('email', 'diego.services@gmail.com')
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
            'email' => 'rosa.services@gmail.com',
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

    public function test_mype_can_create_conversation_and_send_messages_to_freelancer(): void
    {
        Mail::fake();

        config(['services.peru_api.key' => 'fake-key']);

        Http::fake([
            'https://peruapi.com/api/ruc/20608889991*' => Http::response([
                'ruc' => '20608889991',
                'razon_social' => 'MENSAJES MYPE S.A.C.',
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
            'first_name' => 'Nicolas',
            'last_name' => 'Ramos',
            'dni' => '88997766',
            'email' => 'nicolas.messages@gmail.com',
            'password' => 'password123',
        ])->assertCreated();

        $freelancer = User::where('email', 'nicolas.messages@gmail.com')
            ->firstOrFail()
            ->freelancerProfile;

        $mype = $this->postJson('/api/auth/register/mype', [
            'company_name' => 'Mensajes MYPE',
            'ruc' => '20608889991',
            'email' => 'mensajes.mype@gmail.com',
            'password' => 'password123',
        ])->assertCreated();

        $token = $mype->json('data.access_token');

        $created = $this->withToken($token)->postJson('/api/messaging/conversations', [
            'freelancer_profile_id' => $freelancer->id,
            'message' => 'Hola, quiero conversar sobre tu servicio.',
        ]);

        $created->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.message', 'Hola, quiero conversar sobre tu servicio.');

        $conversationId = $created->json('data.conversation.id');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'freelancer_profile_id' => $freelancer->id,
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'message' => 'Hola, quiero conversar sobre tu servicio.',
        ]);

        $this->assertSame(1, Notification::count());

        $second = $this->withToken($token)->postJson('/api/messaging/conversations', [
            'freelancer_profile_id' => $freelancer->id,
            'message' => 'Te envio mas detalle del proyecto.',
        ]);

        $second->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversation.id', $conversationId)
            ->assertJsonPath('data.message.message', 'Te envio mas detalle del proyecto.');

        $this->assertSame(1, Conversation::count());
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'message' => 'Te envio mas detalle del proyecto.',
        ]);
        $this->assertSame(2, Notification::count());

        $this->withToken($token)->getJson("/api/messaging/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.messages');
    }
}
