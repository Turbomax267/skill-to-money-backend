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
            return $this->error('No se configuró la API Key de Gemini.');
        }

        $skills = $data['skills'] ?? [];
        $tools = $data['tools'] ?? [];
        $description = $data['description'] ?? '';
        $areas = $data['areas'] ?? [];
        $certificates = $data['certificates'] ?? [];
        $linkedin = $data['linkedin'] ?? '';
        $instagram = $data['instagram'] ?? '';
        $website = $data['website'] ?? '';

        $prompt = <<<PROMPT
Eres un asesor experto en perfiles freelancer para la plataforma Skill-to-Money (mercado peruano).

Analiza este perfil de freelancer y devuelve SOLO un JSON válido sin markdown ni explicaciones adicionales:

{
  "headline": "Título profesional corto y atractivo (máx 60 caracteres)",
  "category": "Categoría principal (Diseño Gráfico, Edición de Video, Marketing, Desarrollo Web, UX/UI, IA, etc.)",
  "suggested_rate": "Tarifa sugerida por hora en soles peruanos (S/ XX)",
  "bio": "Descripción profesional breve y persuasiva (2-3 oraciones) destacando su valor único",
  "suggested_projects": [
    {"title": "Nombre proyecto sugerido 1", "description": "Descripción breve del proyecto"},
    {"title": "Nombre proyecto sugerido 2", "description": "Descripción breve del proyecto"},
    {"title": "Nombre proyecto sugerido 3", "description": "Descripción breve del proyecto"}
  ],
  "tips": ["Consejo 1 para mejorar su perfil", "Consejo 2", "Consejo 3"]
}

Datos del freelancer:
- Habilidades: {$this->jsonEncode($skills)}
- Herramientas: {$this->jsonEncode($tools)}
- Descripción personal: "{$description}"
- Área de desempeño: {$this->jsonEncode($areas)}
- Certificados: {$this->jsonEncode($certificates)}
- LinkedIn: {$linkedin}
- Instagram: {$instagram}
- Website: {$website}
PROMPT;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 1024,
                ],
            ]);
        } catch (Throwable $e) {
            return $this->error('Error al conectar con Gemini: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            return $this->error('Gemini API respondió con error: ' . $response->body());
        }

        $result = $response->json();
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($text)) {
            return $this->error('Gemini no devolvió contenido.');
        }

        preg_match('/\{[\s\S]*\}/', $text, $matches);

        if (empty($matches)) {
            return $this->error('Gemini no devolvió un JSON válido.');
        }

        $parsed = json_decode($matches[0], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->error('Error al parsear la respuesta de Gemini.');
        }

        return [
            'valid' => true,
            'data' => $parsed,
        ];
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
