<?php

namespace Database\Factories;

use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'slug' => fake()->unique()->slug(2),
            'repository_owner' => 'example',
            'repository_name' => fake()->unique()->slug(2),
            'title' => fake()->words(3, true),
            'summary' => 'A fictional project presentation for the local demo.',
            'overview' => 'Demonstrates how a project will appear in a portfolio. This is seeded content, not a repository analysis.',
            'technical_details' => 'The fictional application validates requests in Laravel, persists records in PostgreSQL and renders its pages with Blade templates.',
            'readme_content' => '# Demo evidence\nNo external repository was fetched for this fixture.',
            'readme_fetched_at' => null,
            'technologies' => ['PHP', 'Laravel'],
            'position' => 0,
        ];
    }
}
