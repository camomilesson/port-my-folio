<?php

namespace Database\Factories;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Portfolio> */
class PortfolioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(3),
            'focus' => 'Backend engineering roles',
            'preset' => 'minimal',
            'accent_color' => '#ea580c',
            'home_content' => ['schema_version' => 1, 'name' => 'Sam Example', 'headline' => 'Web developer', 'introduction' => 'Fictional content for the local demo.'],
            'cv_content' => ['schema_version' => 1, 'name' => 'Sam Example', 'headline' => 'Web developer', 'summary' => 'Fictional demo CV.', 'contact' => ['email' => 'sam@example.com', 'location' => 'Madrid', 'links' => []], 'experience' => [['id' => 'demo-experience', 'organization' => 'Example Studio', 'role' => 'Web developer', 'start_date' => '2024-09', 'end_date' => null, 'description' => 'Built and maintained web applications.']], 'education' => [], 'skills' => ['PHP', 'Laravel'], 'languages' => [], 'allow_original_download' => false],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => now(),
            'published_snapshot' => ['schema_version' => 1, 'preset' => $attributes['preset'], 'accent_color' => $attributes['accent_color'], 'home_content' => $attributes['home_content'], 'cv_content' => $attributes['cv_content'], 'projects' => []],
        ]);
    }
}
