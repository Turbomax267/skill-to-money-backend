<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiService
{
    public function analyzeFreelancer(array $data): array
    {
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            return $this->error('No se configuro la API Key de Gemini.');
        }

        $input = $this->buildInputPayload($data);
        $prompt = $this->buildPrompt($input);

        try {
            $response = Http::timeout((int) config('services.gemini.timeout', 20))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey, [
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
                        'maxOutputTokens' => 2048,
                        'responseMimeType' => 'application/json',
                    ],
                ]);
        } catch (Throwable $e) {
            return $this->error('Error al conectar con Gemini: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            return $this->error('Gemini API respondio con error: ' . $response->body());
        }

        $result = $response->json();
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($text)) {
            return $this->error('Gemini no devolvio contenido.');
        }

        $parsed = $this->decodeGeminiJson($text);

        if (!$parsed) {
            return $this->error('Gemini no devolvio un JSON valido.');
        }

        return [
            'valid' => true,
            'data' => $this->normalizeAnalysis($parsed),
        ];
    }

    private function buildInputPayload(array $data): array
    {
        return [
            'skills' => array_values($data['skills'] ?? []),
            'tools' => array_values($data['tools'] ?? []),
            'description' => $data['description'] ?? null,
            'areas' => array_values($data['areas'] ?? []),
            'certificates' => array_values($data['certificates'] ?? []),
            'social_links' => [
                'linkedin' => $data['linkedin'] ?? null,
                'instagram' => $data['instagram'] ?? null,
                'website' => $data['website'] ?? null,
            ],
            'project_experience' => [
                'has_project_experience' => $data['has_project_experience'] ?? null,
                'projects' => array_values($data['projects'] ?? []),
            ],
            'availability' => [
                'status' => $data['availability'] ?? null,
                'time_to_invest' => $data['availability_time'] ?? null,
            ],
            'freelance_goals' => $data['freelance_goals'] ?? $data['goals'] ?? null,
        ];
    }

    private function buildPrompt(array $input): string
    {
        $payload = $this->jsonEncode($input);

        return <<<PROMPT
Eres un asesor experto en perfiles freelancer para la plataforma Skill-to-Money en el mercado peruano.

Genera recomendaciones profesionales basadas unicamente en habilidades, experiencia, herramientas y objetivos freelance. No hagas inferencias sobre genero, raza, nivel socioeconomico, apariencia, universidad, lugar exacto de residencia o caracteristicas personales no relacionadas con el servicio.

Usa exclusivamente los datos del JSON de entrada. Si falta un dato, responde de forma neutral y no lo inventes.

Devuelve SOLO un JSON valido, sin markdown ni explicaciones adicionales, con esta estructura:
{
  "headline": "Titulo profesional corto y atractivo, maximo 60 caracteres",
  "category": "Categoria principal del servicio",
  "suggested_rate": "Tarifa sugerida por hora en soles peruanos, formato S/ XX",
  "bio": "Descripcion profesional breve y persuasiva de 2 a 3 oraciones",
  "profile_criteria": {
    "positioning": "Como debe posicionarse el freelancer",
    "target_clients": ["Tipo de cliente 1", "Tipo de cliente 2"],
    "service_keywords": ["Keyword 1", "Keyword 2", "Keyword 3"],
    "portfolio_focus": ["Enfoque 1", "Enfoque 2"],
    "pricing_notes": "Criterio breve para sustentar la tarifa"
  },
  "suggested_projects": [
    {
      "title": "Nombre del proyecto sugerido",
      "description": "Descripcion breve del proyecto",
      "estimated_time": "Tiempo estimado",
      "tasks": ["Tarea 1", "Tarea 2", "Tarea 3"]
    }
  ],
  "tips": ["Consejo 1", "Consejo 2", "Consejo 3"],
  "strengths": ["Fortaleza 1", "Fortaleza 2"],
  "availability_summary": "Resumen breve de disponibilidad"
}

Datos del freelancer en JSON:
{$payload}
PROMPT;
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

    private function normalizeAnalysis(array $analysis): array
    {
        $criteria = is_array($analysis['profile_criteria'] ?? null) ? $analysis['profile_criteria'] : [];

        return [
            'headline' => $this->text($analysis['headline'] ?? null),
            'category' => $this->text($analysis['category'] ?? null),
            'suggested_rate' => $this->text($analysis['suggested_rate'] ?? null),
            'bio' => $this->text($analysis['bio'] ?? null),
            'profile_criteria' => [
                'positioning' => $this->text($criteria['positioning'] ?? null),
                'target_clients' => $this->stringList($criteria['target_clients'] ?? [], 6),
                'service_keywords' => $this->stringList($criteria['service_keywords'] ?? [], 8),
                'portfolio_focus' => $this->stringList($criteria['portfolio_focus'] ?? [], 6),
                'pricing_notes' => $this->text($criteria['pricing_notes'] ?? null),
            ],
            'suggested_projects' => $this->projectList($analysis['suggested_projects'] ?? [], 3),
            'tips' => $this->stringList($analysis['tips'] ?? [], 5),
            'strengths' => $this->stringList($analysis['strengths'] ?? [], 5),
            'availability_summary' => $this->text($analysis['availability_summary'] ?? null),
        ];
    }

    private function projectList(mixed $value, int $limit): array
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
                'title' => $this->text($project['title'] ?? null),
                'description' => $this->text($project['description'] ?? null),
                'estimated_time' => $this->text($project['estimated_time'] ?? null),
                'tasks' => $this->stringList($project['tasks'] ?? [], 5),
            ];

            if (count($projects) >= $limit) {
                break;
            }
        }

        return $projects;
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

    private function text(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function jsonEncode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function error(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
        ];
    }
}
