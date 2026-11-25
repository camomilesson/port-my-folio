<?php

use App\Jobs\DeleteUnusedProjectMedia;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('adds a normalized repository-backed project manually without generation', function () {
    $portfolio = Portfolio::factory()->create();

    $this->actingAs($portfolio->user)->post(route('userzone.projects.store', $portfolio), ['repository_url' => 'https://github.com/Example/Project.git', 'title' => 'My project', 'technical_details' => 'The server validates requests and stores application data.', 'technologies_text' => "PHP\nLaravel", 'live_url' => 'https://example.com', 'readme_content' => 'injected', 'portfolio_id' => 999])->assertRedirect()->assertSessionHasNoErrors();

    $project = $portfolio->projects()->sole();
    expect($project->repository_owner)->toBe('example');
    expect($project->repository_name)->toBe('project');
    expect($project->technologies)->toBe(['PHP', 'Laravel']);
    expect($project->technical_details)->toBe('The server validates requests and stores application data.');
    expect($project->readme_content)->toBeNull();
    $this->assertDatabaseCount('generations', 0);
});

it('enforces the project cap and duplicate repository rule', function () {
    $portfolio = Portfolio::factory()->create();
    $project = Project::factory()->for($portfolio)->create();
    $this->actingAs($portfolio->user)->post(route('userzone.projects.store', $portfolio), ['repository_url' => $project->repositoryUrl(), 'title' => 'Duplicate', 'technical_details' => 'The server validates requests and stores application data.', 'author_role' => 'Me'])->assertSessionHasErrors('repository_url');
    Project::factory()->for($portfolio)->count(4)->create();

    $this->post(route('userzone.projects.store', $portfolio), ['repository_url' => 'https://github.com/example/new', 'title' => 'Sixth', 'technical_details' => 'The server validates requests and stores application data.', 'author_role' => 'Me'])->assertSessionHasErrors('repository_url');
    expect($portfolio->projects()->count())->toBe(5);
});

it('refuses unsafe repository and demo URLs', function (array $override) {
    $portfolio = Portfolio::factory()->create();
    $input = array_replace(['repository_url' => 'https://github.com/example/app', 'title' => 'Project', 'technical_details' => 'The server validates requests and stores application data.', 'author_role' => 'Me'], $override);

    $this->actingAs($portfolio->user)->post(route('userzone.projects.store', $portfolio), $input)->assertSessionHasErrors();
    $this->assertDatabaseCount('projects', 0);
})->with([
    [['repository_url' => 'https://github.com.evil.test/example/app']],
    [['repository_url' => 'https://github.com/example/app/tree/main']],
    [['repository_url' => 'http://localhost/private']],
    [['live_url' => 'javascript:alert(1)']],
]);

it('updates text without changing repository identity, evidence, slug, or published content', function () {
    $portfolio = Portfolio::factory()->published()->create();
    $project = Project::factory()->for($portfolio)->create(['readme_content' => 'Evidence']);
    $snapshot = $portfolio->published_snapshot;
    $this->actingAs($portfolio->user);

    $this->put(route('userzone.projects.update', [$portfolio, $project]), ['title' => 'New title', 'technical_details' => 'The server validates requests and stores application data.', 'author_role' => 'New role', 'repository_owner' => 'injected', 'slug' => 'injected', 'readme_content' => 'Changed'])->assertSessionHasNoErrors();
    expect($project->fresh()->title)->toBe('New title');
    expect($project->fresh()->repository_owner)->toBe($project->repository_owner);
    expect($project->fresh()->slug)->toBe($project->slug);
    expect($project->fresh()->readme_content)->toBe('Evidence');
    expect($portfolio->fresh()->published_snapshot)->toBe($snapshot);
    $this->put(route('userzone.projects.update', [$portfolio, $project]), ['title' => 'Another', 'technical_details' => 'The server validates requests and stores application data.', 'author_role' => 'Me', 'repository_url' => 'https://github.com/example/different'])->assertSessionHasErrors('repository_url');
});

it('reorders and soft-removes projects without erasing published state', function () {
    Queue::fake([DeleteUnusedProjectMedia::class]);
    $portfolio = Portfolio::factory()->published()->create();
    $first = Project::factory()->for($portfolio)->create(['position' => 0]);
    $second = Project::factory()->for($portfolio)->create(['position' => 1]);
    $snapshot = $portfolio->published_snapshot;
    $this->actingAs($portfolio->user);

    $this->post(route('userzone.projects.move', [$portfolio, $second]), ['direction' => 'up'])->assertSessionHasNoErrors();
    expect($portfolio->projects()->pluck('id')->all())->toBe([$second->id, $first->id]);
    $this->delete(route('userzone.projects.destroy', [$portfolio, $first]))->assertRedirect();
    $this->assertSoftDeleted($first);
    expect($portfolio->fresh()->published_snapshot)->toBe($snapshot);
    Queue::assertPushed(DeleteUnusedProjectMedia::class, fn ($job) => $job->projectId === $first->id && $job->afterCommit === true);
});

it('rejects mismatched nested IDs even when both portfolios have the same owner', function () {
    $project = Project::factory()->create();
    $other = Portfolio::factory()->for($project->portfolio->user)->create();
    $this->actingAs($project->portfolio->user);

    $this->get(route('userzone.projects.edit', [$other, $project]))->assertNotFound();
    $this->put(route('userzone.projects.update', [$other, $project]), ['title' => 'Injected', 'technical_details' => 'The server validates requests and stores application data.', 'author_role' => 'Me'])->assertNotFound();
    $this->delete(route('userzone.projects.destroy', [$other, $project]))->assertNotFound();
    $this->post(route('userzone.projects.move', [$other, $project]), ['direction' => 'up'])->assertNotFound();
});

it('denies foreign project edits and renders the owner project forms', function () {
    $project = Project::factory()->create();
    $portfolio = $project->portfolio;
    $this->actingAs(User::factory()->create())->get(route('userzone.projects.edit', [$portfolio, $project]))->assertNotFound();
    $this->actingAs($portfolio->user)->get(route('userzone.projects.edit', [$portfolio, $project]))->assertOk()->assertSee('Save project draft');
    $this->get(route('userzone.projects.create', $portfolio))->assertOk()->assertSee('Add project');
});

it('requires technical details when saving a project and preserves the draft on invalid input', function () {
    $project = Project::factory()->create(['technical_details' => 'Existing explanation.']);
    $this->actingAs($project->portfolio->user)->put(route('userzone.projects.update', [$project->portfolio, $project]), ['title' => 'Changed title', 'author_role' => 'Solo developer', 'technical_details' => ''])->assertSessionHasErrors('technical_details');
    expect($project->fresh()->technical_details)->toBe('Existing explanation.');
    expect($project->fresh()->title)->toBe($project->title);
});
