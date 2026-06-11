<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiService
{
    private const PROMPT = <<<'PROMPT'
Eres un asesor experto en freelancing para jÃ³venes principiantes en PerÃº.
Con la informaciÃ³n del freelancer, genera un perfil profesional atractivo, servicios recomendados y mejoras de presentaciÃ³n.
No inventes experiencia que el usuario no haya mencionado.
No exageres habilidades.
No uses datos sensibles.
Los precios deben ser referenciales y en soles peruanos.
Genera exactamente 1 servicio recomendado.
Si has_projects es "no", genera exactamente 3 proyectos prácticos para iniciar portafolio según habilidades y herramientas.
Si has_projects es "si", optimiza solo los proyectos enviados, máximo 3.
Usa textos breves: descripciones de máximo 180 caracteres.
Responde Ãºnicamente en JSON vÃ¡lido con esta estructura:
{"titulo_profesional":"","descripcion_profesional":"","propuesta_valor":"","skills_destacadas":[],"herramientas_destacadas":[],"proyectos_optimizados":[{"nombre":"","descripcion_mejorada":"","categoria":"","herramientas":[]}],"servicios_recomendados":[{"nombre":"","descripcion":"","precio_sugerido":"","tiempo_entrega":"","categoria":""}],"recomendaciones_mejora":[]}
PROMPT;

    public function analyzeFreelancer(array $data): array
    {
        $input = $this->buildInputPayload($data);
        $prompt = self::PROMPT . "\nDatos JSON:\n" . $this->jsonEncode($input);

        $providerErrors = [];

        foreach ([
            fn (): array => $this->callGemini($prompt),
            fn (): array => $this->callOpenRouter($prompt),
            fn (): array => $this->callGroq($prompt),
        ] as $provider) {
            $result = $provider();

            if ($result['valid']) {
                return $result;
            }

            if (($result['code'] ?? null) !== 'not_configured') {
                $providerErrors[] = $result['message'];
            }
        }

        if ((bool) config('services.ai.local_fallback_enabled', false)) {
            return [
                'valid' => true,
                'fallback' => true,
                'source' => 'local_fallback',
                'message' => 'Los proveedores de Skill Bot no estuvieron disponibles. Se generó un perfil básico local para la demo.',
                'data' => $this->normalizeAnalysis($this->buildLocalFallbackAnalysis($input)),
                'provider_errors' => $providerErrors,
            ];
        }

        return $this->error(
            'No se pudo generar el perfil con Skill Bot. Gemini/OpenRouter/Groq no respondieron correctamente. Revisa cuota o API keys e intenta otra vez.',
            503,
            'ai_unavailable',
            $providerErrors,
        );
    }

    private function callGemini(string $prompt): array
    {
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            return $this->error('No se configuró la API Key de Gemini.', 422, 'not_configured');
        }

        try {
            $response = Http::timeout((int) config('services.gemini.timeout', 20))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=' . $apiKey, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.35,
                        'maxOutputTokens' => 1000,
                        'responseMimeType' => 'application/json',
                    ],
                ]);
        } catch (Throwable $e) {
            return $this->error('Error al conectar con Gemini: ' . $e->getMessage());
        }

        if ($response->status() === 429) {
            return $this->error('Gemini alcanzó su limite de cuota.', 429, 'quota_exceeded');
        }

        if (!$response->successful()) {
            return $this->error('Gemini API respondió con error: ' . $response->body());
        }

        $result = $response->json();
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($text)) {
            return $this->error('Gemini no devolvió contenido.');
        }

        return $this->parseProviderJson('gemini', $text, 'Perfil analizado y actualizado exitosamente.');
    }

    private function callOpenRouter(string $prompt): array
    {
        $apiKey = config('services.openrouter.key');

        if (empty($apiKey)) {
            return $this->error('No se configuró la API Key de OpenRouter.', 422, 'not_configured');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ];

        if (!empty(config('services.openrouter.referer'))) {
            $headers['HTTP-Referer'] = config('services.openrouter.referer');
        }

        if (!empty(config('services.openrouter.title'))) {
            $headers['X-Title'] = config('services.openrouter.title');
        }

        try {
            $response = Http::timeout((int) config('services.openrouter.timeout', 20))
                ->withHeaders($headers)
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => config('services.openrouter.model', 'google/gemini-2.5-flash-lite'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Responde únicamente en JSON válido.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.35,
                    'max_tokens' => 1000,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (Throwable $e) {
            return $this->error('Error al conectar con OpenRouter: ' . $e->getMessage());
        }

        if ($response->status() === 429) {
            return $this->error('OpenRouter alcanzó su limite de cuota.', 429, 'quota_exceeded');
        }

        if (!$response->successful()) {
            return $this->error('OpenRouter API respondió con error: ' . $response->body(), $response->status());
        }

        $text = $response->json('choices.0.message.content') ?? '';

        if (empty($text)) {
            return $this->error('OpenRouter no devolvió contenido.');
        }

        return $this->parseProviderJson('openrouter', $text, 'Gemini no estuvo disponible. Perfil generado con OpenRouter.');
    }

    private function callGroq(string $prompt): array
    {
        $apiKey = config('services.groq.key');

        if (empty($apiKey)) {
            return $this->error('No se configuró la API Key de Groq.', 422, 'not_configured');
        }

        try {
            $response = Http::timeout((int) config('services.groq.timeout', 20))
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config('services.groq.model', 'llama-3.1-8b-instant'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Responde únicamente en JSON válido.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.35,
                    'max_tokens' => 1000,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (Throwable $e) {
            return $this->error('Error al conectar con Groq: ' . $e->getMessage());
        }

        if ($response->status() === 429) {
            return $this->error('Groq alcanzó su limite de cuota.', 429, 'quota_exceeded');
        }

        if (!$response->successful()) {
            return $this->error('Groq API respondió con error: ' . $response->body(), $response->status());
        }

        $text = $response->json('choices.0.message.content') ?? '';

        if (empty($text)) {
            return $this->error('Groq no devolvió contenido.');
        }

        return $this->parseProviderJson('groq', $text, 'Gemini/OpenRouter no estuvieron disponibles. Perfil generado con Groq.');
    }

    private function parseProviderJson(string $source, string $text, string $message): array
    {
        $parsed = $this->decodeGeminiJson($text);

        if (!$parsed) {
            return $this->error(ucfirst($source) . ' no devolvió un JSON válido.');
        }

        return [
            'valid' => true,
            'source' => $source,
            'message' => $message,
            'data' => $this->normalizeAnalysis($parsed),
        ];
    }

    private function buildInputPayload(array $data): array
    {
        return [
            'area' => $this->firstText($data['areas'] ?? []),
            'skills' => $this->stringList($data['skills'] ?? [], 5),
            'tools' => $this->stringList($data['tools'] ?? [], 5),
            'description' => $this->text($data['description'] ?? null),
            'certificates' => $this->stringList($data['certificates'] ?? [], 7),
            'has_projects' => $this->text($data['has_project_experience'] ?? null),
            'projects' => $this->inputProjectList($data['projects'] ?? [], 3),
            'availability' => [
                'status' => $this->text($data['availability'] ?? null),
                'time' => $this->text($data['availability_time'] ?? null),
            ],
            'goals' => $this->text($data['freelance_goals'] ?? $data['goals'] ?? null),
        ];
    }

    private function decodeGeminiJson(string $text): ?array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?\s*/', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;

        $parsed = json_decode($clean, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return $parsed;
        }

        preg_match('/\{[\s\S]*\}/', $clean, $matches);

        if (empty($matches[0])) {
            return null;
        }

        $parsed = json_decode($matches[0], true);

        return json_last_error() === JSON_ERROR_NONE && is_array($parsed) ? $parsed : null;
    }

    private function buildLocalFallbackAnalysis(array $input): array
    {
        $skills = $this->stringList($input['skills'] ?? [], 5);
        $tools = $this->stringList($input['tools'] ?? [], 5);
        $projects = $this->inputProjectList($input['projects'] ?? [], 3);
        $primarySkill = $skills[0] ?? 'servicios digitales';
        $primaryTool = $tools[0] ?? 'herramientas digitales';
        $category = $this->text($input['area'] ?? null) ?? $primarySkill;
        $description = $this->text($input['description'] ?? null)
            ?? 'Freelancer en etapa inicial con interes en desarrollar servicios digitales para clientes.';
        $availabilityTime = is_array($input['availability'] ?? null)
            ? $this->text($input['availability']['time'] ?? null)
            : null;

        return [
            'titulo_profesional' => $this->titleCase($primarySkill) . ' para negocios digitales',
            'descripcion_profesional' => $description,
            'propuesta_valor' => "Ayudo a clientes a resolver necesidades digitales usando {$primarySkill} y {$primaryTool}, con entregas claras y enfoque práctico.",
            'skills_destacadas' => $skills,
            'herramientas_destacadas' => $tools,
            'proyectos_optimizados' => $this->fallbackProjects($projects, $primarySkill, $category, $tools),
            'servicios_recomendados' => [
                [
                    'nombre' => 'Servicio inicial de ' . $primarySkill,
                    'descripcion' => 'Servicio básico orientado a resolver una necesidad concreta del cliente con entregables simples y revisables.',
                    'precio_sugerido' => 'S/ 30 por hora',
                    'tiempo_entrega' => $availabilityTime ?: '3 a 5 dias',
                    'categoria' => $category,
                ],
                [
                    'nombre' => 'Mejora de presencia digital',
                    'descripcion' => 'Apoyo para ordenar, mejorar o presentar mejor un activo digital según las habilidades declaradas.',
                    'precio_sugerido' => 'S/ 120 por proyecto',
                    'tiempo_entrega' => '5 a 7 dias',
                    'categoria' => $category,
                ],
            ],
            'recomendaciones_mejora' => [
                'Agrega ejemplos visuales o capturas de tus trabajos.',
                'Describe cada proyecto con objetivo, proceso y resultado.',
                'Define paquetes simples con precio y tiempo de entrega.',
            ],
        ];
    }

    private function fallbackProjects(array $projects, string $primarySkill, string $category, array $tools): array
    {
        if (empty($projects)) {
            return [
                [
                    'nombre' => 'Proyecto inicial de ' . $primarySkill,
                    'descripcion_mejorada' => 'Proyecto práctico para demostrar habilidades principales y construir evidencia de trabajo.',
                    'categoria' => $category,
                    'herramientas' => $tools,
                ],
            ];
        }

        return array_map(fn (array $project): array => [
            'nombre' => $this->text($project['name'] ?? null) ?? 'Proyecto de portafolio',
            'descripcion_mejorada' => $this->text($project['description'] ?? null)
                ?? 'Proyecto presentado como evidencia de habilidades y proceso de trabajo.',
            'categoria' => $this->text($project['category'] ?? null) ?? $category,
            'herramientas' => $this->stringList($project['tools'] ?? $tools, 6),
        ], $projects);
    }

    private function normalizeAnalysis(array $analysis): array
    {
        $projects = $this->optimizedProjectList($analysis['proyectos_optimizados'] ?? [], 3);
        $services = $this->recommendedServiceList($analysis['servicios_recomendados'] ?? [], 5);
        $skills = $this->stringList($analysis['skills_destacadas'] ?? [], 8);
        $tools = $this->stringList($analysis['herramientas_destacadas'] ?? [], 8);
        $tips = $this->stringList($analysis['recomendaciones_mejora'] ?? [], 8);
        $firstService = $services[0] ?? [];
        $firstProject = $projects[0] ?? [];

        return [
            'titulo_profesional' => $this->text($analysis['titulo_profesional'] ?? null),
            'descripcion_profesional' => $this->text($analysis['descripcion_profesional'] ?? null),
            'propuesta_valor' => $this->text($analysis['propuesta_valor'] ?? null),
            'skills_destacadas' => $skills,
            'herramientas_destacadas' => $tools,
            'proyectos_optimizados' => $projects,
            'servicios_recomendados' => $services,
            'recomendaciones_mejora' => $tips,
            'headline' => $this->text($analysis['titulo_profesional'] ?? null),
            'category' => $this->text($firstService['categoria'] ?? $firstProject['categoria'] ?? null),
            'suggested_rate' => $this->text($firstService['precio_sugerido'] ?? null),
            'bio' => $this->text($analysis['descripcion_profesional'] ?? null),
            'profile_criteria' => [
                'positioning' => $this->text($analysis['propuesta_valor'] ?? null),
                'target_clients' => [],
                'service_keywords' => $skills,
                'portfolio_focus' => array_values(array_filter(array_column($projects, 'nombre'))),
                'pricing_notes' => $this->text($firstService['precio_sugerido'] ?? null),
            ],
            'suggested_projects' => $this->legacyProjectList($projects, 3),
            'tips' => $tips,
            'strengths' => $skills,
            'availability_summary' => $this->text($analysis['availability_summary'] ?? null),
        ];
    }

    private function inputProjectList(mixed $value, int $limit): array
    {
        if (!is_array($value)) {
            return [];
        }

        $projects = [];

        foreach ($value as $project) {
            if (!is_array($project)) {
                continue;
            }

            $projects[] = [
                'name' => $this->text($project['name'] ?? $project['title'] ?? null),
                'description' => $this->text($project['description'] ?? null),
                'tools' => $this->stringList($project['tools'] ?? [], 5),
                'time' => $this->text($project['time'] ?? $project['estimated_time'] ?? null),
                'category' => $this->text($project['category'] ?? null),
            ];

            if (count($projects) >= $limit) {
                break;
            }
        }

        return $projects;
    }

    private function optimizedProjectList(mixed $value, int $limit): array
    {
        if (!is_array($value)) {
            return [];
        }

        $projects = [];

        foreach ($value as $project) {
            if (!is_array($project)) {
                continue;
            }

            $projects[] = [
                'nombre' => $this->text($project['nombre'] ?? null),
                'descripcion_mejorada' => $this->text($project['descripcion_mejorada'] ?? null),
                'categoria' => $this->text($project['categoria'] ?? null),
                'herramientas' => $this->stringList($project['herramientas'] ?? [], 6),
            ];

            if (count($projects) >= $limit) {
                break;
            }
        }

        return $projects;
    }

    private function recommendedServiceList(mixed $value, int $limit): array
    {
        if (!is_array($value)) {
            return [];
        }

        $services = [];

        foreach ($value as $service) {
            if (!is_array($service)) {
                continue;
            }

            $services[] = [
                'nombre' => $this->text($service['nombre'] ?? null),
                'descripcion' => $this->text($service['descripcion'] ?? null),
                'precio_sugerido' => $this->text($service['precio_sugerido'] ?? null),
                'tiempo_entrega' => $this->text($service['tiempo_entrega'] ?? null),
                'categoria' => $this->text($service['categoria'] ?? null),
            ];

            if (count($services) >= $limit) {
                break;
            }
        }

        return $services;
    }

    private function legacyProjectList(array $projects, int $limit): array
    {
        return array_slice(array_map(fn (array $project): array => [
            'title' => $this->text($project['nombre'] ?? null),
            'description' => $this->text($project['descripcion_mejorada'] ?? null),
            'estimated_time' => null,
            'tasks' => [],
        ], $projects), 0, $limit);
    }

    private function stringList(mixed $value, int $limit): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $text = $this->text($item);

            if ($text === null) {
                continue;
            }

            $items[] = $text;

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function firstText(mixed $value): ?string
    {
        $items = $this->stringList($value, 1);

        return $items[0] ?? null;
    }

    private function text(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function titleCase(string $value): string
    {
        return ucwords(strtolower($value));
    }

    private function jsonEncode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function error(string $message, int $status = 422, ?string $code = null, array $providerErrors = []): array
    {
        return [
            'valid' => false,
            'message' => $message,
            'status' => $status,
            'code' => $code,
            'provider_errors' => $providerErrors,
        ];
    }
}

