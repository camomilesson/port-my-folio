<?php

namespace Database\Seeders;

use App\Models\CvDocument;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use LogicException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo data may only be seeded locally or in tests.');
        }

        fake()->seed(20261124);
        $owner = User::factory()->create(['google_id' => 'demo-admin', 'email' => 'admin@example.com', 'name' => 'Sam Example', 'username' => 'sam-example']);
        $other = User::factory()->create(['google_id' => 'demo-other', 'email' => 'alex@example.com', 'name' => 'Alex Example', 'username' => 'alex-example']);

        foreach ([$owner, $other] as $user) {
            $portfolio = Portfolio::factory()->for($user)->create(['title' => 'Backend developer', 'slug' => 'backend-developer']);
            $document = CvDocument::factory()->for($portfolio)->create();
            Storage::disk(config('portmyfolio.cv_disk'))->put($document->storage_path, file_get_contents(resource_path('fixtures/demo-cv.pdf')));

            foreach (['laravel/laravel', 'tailwindlabs/tailwindcss'] as $position => $repository) {
                [$repositoryOwner, $repositoryName] = explode('/', $repository);
                $project = Project::factory()->for($portfolio)->create(['slug' => $repositoryName, 'repository_owner' => $repositoryOwner, 'repository_name' => $repositoryName, 'author_role' => 'Fictional role for demonstration only', 'position' => $position]);
                $project->addMedia(resource_path('fixtures/demo-project.png'))->preservingOriginal()->withCustomProperties(['caption' => 'Synthetic demo screenshot', 'alt_text' => 'Portmyfolio demo project placeholder', 'draft_removed' => false])->toMediaCollection('gallery');
            }

            Generation::factory()->for($portfolio)->succeeded()->create();
        }

        Portfolio::factory()->for($owner)->create(['title' => 'A new idea', 'slug' => 'new-idea', 'focus' => null, 'home_content' => null, 'cv_content' => null]);
        $published = Portfolio::factory()->for($owner)->published()->create(['title' => 'Published example', 'slug' => 'published-example']);
        $owner->forceFill(['default_portfolio_id' => $published->id])->save();
    }
}
