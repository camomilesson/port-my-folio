<?php

namespace Database\Factories;

use App\Models\CvDocument;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

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
            'cv_content' => ['schema_version' => 1, 'name' => 'Sam Example', 'headline' => 'Web developer', 'summary' => 'Fictional demo CV.', 'contact' => ['email' => 'sam@example.com', 'location' => 'Madrid', 'links' => []], 'experience' => [['id' => 'demo-experience', 'organization' => 'Example Studio', 'role' => 'Web developer', 'start_date' => '2024-09', 'end_date' => null, 'current' => true, 'description' => 'Built and maintained web applications.']], 'education' => [], 'skills' => [['id' => 'demo-skills', 'name' => 'Backend', 'items' => ['PHP', 'Laravel']]], 'languages' => [], 'certifications' => []],
        ];
    }

    public function generated(): static
    {
        return $this->afterCreating(function (Portfolio $portfolio): void {
            $document = CvDocument::factory()->for($portfolio)->create(['storage_disk' => config('portmyfolio.cv_disk')]);
            Storage::disk($document->storageDisk())->put($document->storage_path, file_get_contents(resource_path('fixtures/demo-cv.pdf')));
            Project::factory()->for($portfolio)->create();
            Generation::factory()->for($portfolio)->succeeded()->create();
        });
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => now(),
            'published_snapshot' => ['schema_version' => 1, 'preset' => $attributes['preset'], 'accent_color' => $attributes['accent_color'], 'home_content' => $attributes['home_content'], 'cv_content' => $attributes['cv_content'], 'projects' => []],
        ]);
    }
}
