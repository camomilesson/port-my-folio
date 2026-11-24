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
            'author_role' => 'Solo developer (fictional demo)',
            'title' => fake()->words(3, true),
            'summary' => 'A fictional project presentation for the local demo.',
            'overview' => 'Demonstrates how a project will appear in a portfolio. This is seeded content, not a repository analysis.',
            'readme_content' => '# Demo evidence\nNo external repository was fetched for this fixture.',
            'readme_fetched_at' => null,
            'technologies' => ['PHP', 'Laravel'],
            'position' => 0,
        ];
    }
}
