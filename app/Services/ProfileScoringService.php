<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\FreelancerProfile;
use Illuminate\Support\Str;

class ProfileScoringService
{
    private const WEIGHTS = [
        'skills' => 40,
        'category' => 20,
        'rating' => 20,
        'experience' => 20,
    ];

    private const TERM_ALIASES = [
        'web' => ['web', 'frontend', 'backend', 'fullstack', 'full stack', 'react', 'javascript', 'landing', 'ecommerce', 'shopify', 'wordpress'],
        'pagina' => ['pagina', 'landing', 'web', 'frontend'],
        'landing' => ['landing', 'web', 'frontend', 'react'],
        'frontend' => ['frontend', 'react', 'javascript', 'web'],
        'backend' => ['backend', 'api', 'laravel', 'php', 'node'],
        'fullstack' => ['fullstack', 'full stack', 'frontend', 'backend', 'web'],
        'full' => ['full stack', 'fullstack', 'frontend', 'backend', 'web'],
        'video' => ['video', 'videos', 'edicion', 'reels', 'tiktok', 'capcut', 'premiere', 'after effects', 'motion', 'animacion', 'shorts'],
        'videos' => ['video', 'videos', 'edicion', 'reels', 'tiktok', 'capcut', 'premiere', 'after effects', 'motion', 'animacion', 'shorts'],
        'reels' => ['reels', 'video', 'tiktok', 'shorts', 'capcut', 'premiere'],
        'edicion' => ['edicion', 'video', 'premiere', 'capcut', 'after effects'],
        'animacion' => ['animacion', 'motion', 'after effects', 'video'],
        'diseno' => ['diseno', 'grafico', 'branding', 'logo', 'ilustracion', 'dibujo', 'figma', 'canva', 'photoshop', 'illustrator'],
        'grafico' => ['grafico', 'diseno', 'branding', 'logo', 'canva', 'photoshop', 'illustrator'],
        'branding' => ['branding', 'marca', 'logo', 'identidad', 'diseno', 'grafico'],
        'logo' => ['logo', 'branding', 'marca', 'identidad', 'illustrator', 'diseno'],
        'dibujo' => ['dibujo', 'ilustracion', 'illustrator', 'photoshop', 'arte', 'personaje', 'boceto'],
        'ilustracion' => ['ilustracion', 'dibujo', 'illustrator', 'photoshop', 'arte', 'personaje'],
        'excel' => ['excel', 'dashboard', 'macros', 'vba', 'tablas', 'power bi', 'powerbi', 'data', 'datos', 'reportes'],
        'dashboard' => ['dashboard', 'excel', 'power bi', 'powerbi', 'data', 'datos', 'reportes'],
        'datos' => ['datos', 'data', 'excel', 'power bi', 'powerbi', 'dashboard', 'analisis', 'reportes'],
        'data' => ['data', 'datos', 'excel', 'power bi', 'powerbi', 'dashboard', 'analisis', 'reportes'],
        'reportes' => ['reportes', 'excel', 'dashboard', 'power bi', 'powerbi', 'datos'],
        'marketing' => ['marketing', 'redes', 'social', 'contenido', 'copywriting', 'instagram', 'facebook', 'tiktok', 'campanas'],
        'redes' => ['redes', 'social', 'contenido', 'instagram', 'facebook', 'tiktok', 'community', 'marketing'],
        'contenido' => ['contenido', 'copywriting', 'redes', 'marketing', 'instagram', 'tiktok'],
        'copywriting' => ['copywriting', 'copy', 'contenido', 'marketing', 'redaccion'],
        'automatizacion' => ['automatizacion', 'automation', 'ia', 'inteligencia artificial', 'n8n', 'make', 'zapier', 'chatbot', 'bot'],
        'ia' => ['ia', 'inteligencia artificial', 'automatizacion', 'chatbot', 'prompt', 'n8n', 'make'],
        'ux' => ['ux', 'ui', 'figma', 'prototipo', 'wireframe', 'research', 'usabilidad', 'experiencia'],
        'ui' => ['ui', 'ux', 'figma', 'prototipo', 'wireframe', 'interfaz', 'usabilidad'],
        'prototipo' => ['prototipo', 'figma', 'ux', 'ui', 'wireframe'],
    ];

    public function compatibility(FreelancerProfile $profile, array $context = [], ?ClientProject $project = null): array
    {
        $profile->loadMissing(['user', 'skills', 'portfolioProjects', 'services']);

        $projectText = $this->normalize(implode(' ', [
            $project?->title,
            $project?->description,
            $project?->category,
            $context['search'] ?? null,
            $context['skill'] ?? null,
        ]));

        $profileSkills = $profile->skills->pluck('name')->values()->all();
        $skillMatches = $this->skillMatches($profileSkills, $projectText, $context['skill'] ?? null);
        $skillsScore = empty($skillMatches['requested'])
            ? 0
            : min(1, count($skillMatches['matched_terms']) / max(1, count($skillMatches['requested'])));

        $categoryScore = $this->categoryScore($profile, $project?->category ?? ($context['category'] ?? null));
        $ratingScore = min(1, max(0, ((float) $profile->rating) / 5));
        $experienceScore = min(1, max(0, ((int) $profile->completed_jobs) / 5));

        $points = [
            'skills' => round($skillsScore * self::WEIGHTS['skills'], 2),
            'category' => round($categoryScore * self::WEIGHTS['category'], 2),
            'rating' => round($ratingScore * self::WEIGHTS['rating'], 2),
            'experience' => round($experienceScore * self::WEIGHTS['experience'], 2),
        ];

        $score = round(array_sum($points), 2);
        $rate = $this->parseRate($profile->suggested_rate);
        $maxBudget = $project?->budget_max !== null ? (float) $project->budget_max : ($context['max_rate'] ?? null);
        $minBudget = $project?->budget_min !== null ? (float) $project->budget_min : ($context['min_rate'] ?? null);
        $rateInRange = $this->rateInRange($rate, $minBudget !== null ? (float) $minBudget : null, $maxBudget !== null ? (float) $maxBudget : null);

        return [
            'score' => min(100, $score),
            'level' => $this->scoreLevel($score),
            'breakdown' => [
                'skills' => [
                    'weight' => self::WEIGHTS['skills'],
                    'points' => $points['skills'],
                    'requested' => $skillMatches['requested'],
                    'matched' => $skillMatches['matched'],
                ],
                'category' => [
                    'weight' => self::WEIGHTS['category'],
                    'points' => $points['category'],
                    'matched' => $categoryScore > 0,
                    'requested' => $project?->category ?? ($context['category'] ?? null),
                    'profile_value' => $profile->category,
                ],
                'rating' => [
                    'weight' => self::WEIGHTS['rating'],
                    'points' => $points['rating'],
                    'value' => (float) $profile->rating,
                ],
                'experience' => [
                    'weight' => self::WEIGHTS['experience'],
                    'points' => $points['experience'],
                    'completed_jobs' => (int) $profile->completed_jobs,
                ],
                'price_range' => [
                    'matched' => $rateInRange,
                    'rate_amount' => $rate,
                    'budget_min' => $minBudget !== null ? (float) $minBudget : null,
                    'budget_max' => $maxBudget !== null ? (float) $maxBudget : null,
                ],
            ],
            'reasons' => $this->compatibilityReasons($profile, $points, $skillMatches, $categoryScore, $rateInRange),
        ];
    }

    public function visibility(FreelancerProfile $profile): array
    {
        $profile->loadMissing(['skills', 'portfolioProjects']);

        $socialLinks = $profile->social_links ?? [];
        $checks = [
            'photo' => [
                'label' => 'Foto de perfil',
                'done' => filled($profile->profile_photo),
                'points' => 20,
            ],
            'description' => [
                'label' => 'Descripcion profesional',
                'done' => filled($profile->bio) && mb_strlen((string) $profile->bio) >= 80,
                'points' => 20,
            ],
            'skills' => [
                'label' => 'Habilidades',
                'done' => $profile->skills->count() >= 3,
                'points' => 20,
            ],
            'portfolio' => [
                'label' => 'Portafolio',
                'done' => $profile->portfolioProjects->count() > 0,
                'points' => 20,
            ],
            'social_links' => [
                'label' => 'Redes o sitio web',
                'done' => filled($profile->website)
                    || filled($socialLinks['linkedin'] ?? null)
                    || filled($socialLinks['instagram'] ?? null)
                    || filled($socialLinks['website'] ?? null),
                'points' => 20,
            ],
        ];

        $score = collect($checks)
            ->filter(fn (array $check): bool => $check['done'])
            ->sum('points');

        return [
            'score' => $score,
            'level' => match (true) {
                $score >= 80 => 'Alta',
                $score >= 60 => 'Media',
                default => 'Baja',
            },
            'tier' => match (true) {
                $score >= 80 => 'Oro',
                $score >= 60 => 'Plata',
                default => 'Bronce',
            },
            'checks' => array_values($checks),
            'missing' => collect($checks)
                ->filter(fn (array $check): bool => ! $check['done'])
                ->pluck('label')
                ->values()
                ->all(),
        ];
    }

    public function portfolioHealth(FreelancerProfile $profile): array
    {
        $profile->loadMissing(['skills', 'portfolioProjects']);

        $projectCount = $profile->portfolioProjects->count();
        $skillCount = $profile->skills->count();
        $hasPhoto = filled($profile->profile_photo);
        $hasDescription = filled($profile->bio) && mb_strlen((string) $profile->bio) >= 80;

        $score = 0;
        $score += $hasPhoto ? 20 : 0;
        $score += $hasDescription ? 20 : 0;
        $score += min(20, $skillCount * 7);
        $score += min(30, $projectCount * 15);
        $score += filled($profile->headline) ? 10 : 0;

        $recommendations = [];

        if (! $hasPhoto) {
            $recommendations[] = 'Agrega una foto clara para generar mas confianza.';
        }

        if (! $hasDescription) {
            $recommendations[] = 'Amplia tu descripcion profesional hasta explicar que haces, para quien y con que herramientas.';
        }

        if ($skillCount < 3) {
            $recommendations[] = 'Agrega al menos 3 habilidades relacionadas con tu servicio principal.';
        }

        if ($projectCount < 3) {
            $recommendations[] = 'Agrega mas proyectos de portafolio para demostrar resultados y proceso.';
        }

        if (! filled($profile->headline)) {
            $recommendations[] = 'Define un titulo profesional breve y orientado a clientes.';
        }

        return [
            'score' => min(100, $score),
            'level' => match (true) {
                $score >= 80 => 'Portafolio fuerte',
                $score >= 50 => 'Portafolio en progreso',
                default => 'Portafolio inicial',
            },
            'signals' => [
                'has_photo' => $hasPhoto,
                'has_description' => $hasDescription,
                'projects_count' => $projectCount,
                'skills_count' => $skillCount,
                'has_headline' => filled($profile->headline),
            ],
            'recommendations' => $recommendations,
        ];
    }

    public function parseRate(?string $rate): ?float
    {
        if ($rate === null) {
            return null;
        }

        if (! preg_match('/\d+(?:[.,]\d+)?/', $rate, $matches)) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[0]);
    }

    private function skillMatches(array $skills, string $projectText, ?string $explicitSkill): array
    {
        $explicitTerms = $this->importantTerms((string) $explicitSkill);
        $sourceTerms = ! empty($explicitTerms) ? $explicitTerms : $this->importantTerms($projectText);

        $requested = collect($sourceTerms)
            ->unique()
            ->values()
            ->all();

        $matched = [];
        $matchedTerms = [];

        foreach ($requested as $term) {
            $termAliases = $this->expandTerm($term);

            foreach ($skills as $skill) {
                $normalizedSkill = $this->normalize($skill);
                $skillAliases = $this->expandTerm($normalizedSkill);

                foreach ($termAliases as $termAlias) {
                    foreach ($skillAliases as $skillAlias) {
                        if (
                            $this->termsOverlap($normalizedSkill, $termAlias)
                            || $this->termsOverlap($skillAlias, $termAlias)
                        ) {
                            $matched[] = $skill;
                            $matchedTerms[] = $term;
                            break 3;
                        }
                    }
                }
            }
        }

        return [
            'requested' => $requested,
            'matched' => array_values(array_unique($matched)),
            'matched_terms' => array_values(array_unique($matchedTerms)),
        ];
    }

    private function categoryScore(FreelancerProfile $profile, ?string $category): float
    {
        if (! filled($category)) {
            return filled($profile->category) ? 0.5 : 0;
        }

        $requested = $this->normalize((string) $category);
        $requestedTerms = collect(explode(' ', $requested))
            ->flatMap(fn (string $term): array => $this->expandTerm($term))
            ->push($requested)
            ->unique()
            ->values()
            ->all();
        $profileCategory = $this->normalize((string) $profile->category);
        $experienceArea = $this->normalize((string) $profile->experience_area);
        $profileText = $this->normalize(implode(' ', [
            $profile->category,
            $profile->experience_area,
            $profile->headline,
            $profile->bio,
        ]));

        if ($profileCategory !== '' && (str_contains($profileCategory, $requested) || str_contains($requested, $profileCategory))) {
            return 1;
        }

        if ($experienceArea !== '' && (str_contains($experienceArea, $requested) || str_contains($requested, $experienceArea))) {
            return 0.8;
        }

        foreach ($requestedTerms as $term) {
            if ($term !== '' && str_contains($profileText, $term)) {
                return 0.8;
            }
        }

        return 0;
    }

    private function compatibilityReasons(
        FreelancerProfile $profile,
        array $points,
        array $skillMatches,
        float $categoryScore,
        ?bool $rateInRange,
    ): array {
        $reasons = [];

        if ($points['skills'] > 0) {
            $reasons[] = 'Habilidades coincidentes: ' . implode(', ', $skillMatches['matched']);
        }

        if ($categoryScore > 0) {
            $reasons[] = 'Coincide con la categoria solicitada.';
        }

        if ((float) $profile->rating >= 4.5) {
            $reasons[] = 'Tiene buena reputacion.';
        }

        if ((int) $profile->completed_jobs > 0) {
            $reasons[] = 'Tiene experiencia registrada en la plataforma.';
        }

        if ($rateInRange === true) {
            $reasons[] = 'Su tarifa esta dentro del rango indicado.';
        }

        if ($rateInRange === false) {
            $reasons[] = 'Su tarifa esta fuera del rango indicado.';
        }

        return array_values(array_unique(array_filter($reasons)));
    }

    private function rateInRange(?float $rate, ?float $min, ?float $max): ?bool
    {
        if ($rate === null || ($min === null && $max === null)) {
            return null;
        }

        if ($min !== null && $rate < $min) {
            return false;
        }

        if ($max !== null && $rate > $max) {
            return false;
        }

        return true;
    }

    private function importantTerms(string $text): array
    {
        $stopwords = [
            'para', 'con', 'los', 'las', 'una', 'uno', 'del', 'por', 'que', 'como',
            'servicio', 'servicios', 'proyecto', 'proyectos', 'necesito', 'quiero',
            'crear', 'hacer', 'digital', 'mype', 'cliente',
        ];

        return collect(explode(' ', $this->normalize($text)))
            ->map(fn (string $word): string => trim($word))
            ->filter(fn (string $word): bool => strlen($word) >= 3 && ! in_array($word, $stopwords, true))
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    private function expandTerm(string $term): array
    {
        $term = $this->normalize($term);

        return self::TERM_ALIASES[$term] ?? [$term];
    }

    private function termsOverlap(string $left, string $right): bool
    {
        return $left !== ''
            && $right !== ''
            && (str_contains($left, $right) || str_contains($right, $left));
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();
    }

    private function scoreLevel(float $score): string
    {
        return match (true) {
            $score >= 80 => 'Alta compatibilidad',
            $score >= 60 => 'Buena compatibilidad',
            $score >= 40 => 'Compatibilidad media',
            default => 'Compatibilidad baja',
        };
    }
}
