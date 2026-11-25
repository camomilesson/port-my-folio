<?php

namespace App\Actions;

class FakePortfolioProvider implements PortfolioGenerationProvider
{
    public function extractCv(string $pdfBytes): ProviderResult
    {
        return new ProviderResult(GenerationContent::validate(self::cv(), true), 'fixture', 'deterministic-v1', 'portfolio-v1', []);
    }

    /** @param array<string, mixed> $evidence */
    public function generateContent(array $evidence): ProviderResult
    {
        $projects = [];
        foreach ($evidence['projects'] ?? [] as $project) {
            $projects[] = ['repository_owner' => $project['repository_owner'], 'repository_name' => $project['repository_name'], 'title' => $project['repository_name'], 'summary' => '', 'overview' => '', 'technologies' => [], 'live_url' => null];
        }
        $content = ['home_content' => ['name' => 'Sam Example', 'headline' => 'Developer', 'introduction' => ''], 'cv_content' => self::cv(), 'projects' => $projects];

        return new ProviderResult(GenerationContent::validate($content, false), 'fixture', 'deterministic-v1', 'portfolio-v1', []);
    }

    /** @return array<string, mixed> */
    public static function cv(): array
    {
        return ['name' => 'Sam Example', 'headline' => 'Developer', 'summary' => '', 'contact' => ['email' => null, 'phone' => null, 'location' => null, 'links' => []], 'experience' => [], 'education' => [], 'skills' => [], 'languages' => [], 'certifications' => []];
    }
}
