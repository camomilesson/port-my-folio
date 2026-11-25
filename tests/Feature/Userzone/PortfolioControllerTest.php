<?php

use App\Jobs\DeletePortfolioFiles;
use App\Models\CvDocument;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('requires authentication for portfolio management', function () {
    $portfolio = Portfolio::factory()->create();
    $this->get(route('userzone.portfolios.create'))->assertRedirect(route('login'));
    $this->post(route('userzone.portfolios.store'), [])->assertRedirect(route('login'));
    $this->get(route('userzone.portfolios.show', $portfolio))->assertNotFound();
    $this->delete(route('userzone.portfolios.destroy', $portfolio))->assertRedirect(route('login'));
    $this->assertModelExists($portfolio);
});

it('requires a username before creating drafts', function () {
    $owner = User::factory()->withoutUsername()->create();
    $this->actingAs($owner)->post(route('userzone.portfolios.store'), ['title' => 'Backend', 'slug' => 'backend'])
        ->assertRedirect(route('userzone.username.edit'));
    $this->assertDatabaseCount('portfolios', 0);
});

it('creates a private draft for the signed-in owner and ignores privileged fields', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($owner)->post(route('userzone.portfolios.store'), ['title' => 'Backend applications', 'slug' => '  Backend-Roles ', 'focus' => 'Backend jobs', 'user_id' => $other->id, 'preset' => 'injected', 'published_snapshot' => ['injected' => true], 'published_at' => '2026-01-01'])
        ->assertRedirect(route('userzone.portfolios.show', Portfolio::sole()));

    $this->assertDatabaseHas('portfolios', ['user_id' => $owner->id, 'title' => 'Backend applications', 'slug' => 'backend-roles', 'preset' => 'minimal', 'published_at' => null, 'published_snapshot' => null]);
});

it('validates draft fields with useful feedback', function (array $input, array $errors) {
    $this->actingAs(User::factory()->create())->from(route('userzone.portfolios.create'))
        ->post(route('userzone.portfolios.store'), $input)
        ->assertRedirect(route('userzone.portfolios.create'))->assertSessionHasErrors($errors);
    $this->assertDatabaseCount('portfolios', 0);
})->with([
    'required fields' => [[], ['title' => 'The title field is required.', 'slug' => 'The slug field is required.']],
    'long title' => [['title' => str_repeat('a', 121), 'slug' => 'backend'], ['title' => 'The title field must not be greater than 120 characters.']],
    'invalid slug' => [['title' => 'Backend', 'slug' => 'not/a/path'], ['slug' => 'Use lowercase letters, numbers, and single hyphens for the address.']],
    'long slug' => [['title' => 'Backend', 'slug' => str_repeat('a', 65)], ['slug' => 'The slug field must not be greater than 64 characters.']],
    'long focus' => [['title' => 'Backend', 'slug' => 'backend', 'focus' => str_repeat('a', 5001)], ['focus' => 'The focus field must not be greater than 5000 characters.']],
]);

it('checks slug uniqueness within an owner rather than across accounts', function () {
    $owner = User::factory()->create();
    Portfolio::factory()->create(['slug' => 'backend']);
    $this->actingAs($owner)->post(route('userzone.portfolios.store'), ['title' => 'Backend', 'slug' => 'backend'])->assertRedirect();
    $this->post(route('userzone.portfolios.store'), ['title' => 'Duplicate', 'slug' => 'backend'])
        ->assertSessionHasErrors(['slug' => 'You already have a portfolio with this address.']);
    expect($owner->portfolios()->count())->toBe(1);
});

it('shows only owned portfolios on the dashboard', function () {
    $owner = User::factory()->create();
    Portfolio::factory()->for($owner)->create(['title' => 'My backend portfolio']);
    Portfolio::factory()->create(['title' => 'Secret other portfolio']);

    $this->actingAs($owner)->get(route('dashboard'))->assertOk()
        ->assertSee('My backend portfolio')->assertDontSee('Secret other portfolio');
});

it('escapes draft text on its owner page', function () {
    $portfolio = Portfolio::factory()->create(['title' => '<script>alert(1)</script>', 'focus' => '<img src=x onerror=alert(1)>']);

    $this->actingAs($portfolio->user)->get(route('userzone.portfolios.show', $portfolio))->assertOk()
        ->assertSee($portfolio->title)->assertSee($portfolio->focus)
        ->assertDontSee($portfolio->title, false)->assertDontSee($portfolio->focus, false);
});

it('does not reveal another owners draft through guessed IDs', function () {
    Queue::fake([DeletePortfolioFiles::class]);
    $portfolio = Portfolio::factory()->create();
    $this->actingAs(User::factory()->create())->get(route('userzone.portfolios.show', $portfolio))->assertNotFound();
    $this->delete(route('userzone.portfolios.destroy', $portfolio))->assertNotFound();
    $this->assertModelExists($portfolio);
    Queue::assertNothingPushed();
});

it('deletes a draft while retaining redacted quota history and deferring file cleanup', function () {
    Storage::fake('images_local');
    Storage::fake('local');
    $portfolio = Portfolio::factory()->create();
    $project = Project::factory()->for($portfolio)->create();
    $media = $project->addMedia(UploadedFile::fake()->image('sample.png'))->toMediaCollection('gallery');
    $document = CvDocument::factory()->for($portfolio)->create();
    Storage::disk('local')->put($document->storage_path, 'fixture');
    $generation = Generation::factory()->for($portfolio)->succeeded()->create();
    Queue::fake([DeletePortfolioFiles::class]);

    $this->actingAs($portfolio->user)->delete(route('userzone.portfolios.destroy', $portfolio))->assertRedirect(route('dashboard'));

    $this->assertModelMissing($portfolio);
    $this->assertDatabaseCount('projects', 0);
    $this->assertDatabaseCount('cv_documents', 0);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'portfolio_id' => null, 'status' => 'succeeded', 'input_snapshot' => null, 'progress' => null]);
    $this->assertModelExists($media);
    Storage::disk('local')->assertExists($document->storage_path);
    Queue::assertPushed(DeletePortfolioFiles::class, fn (DeletePortfolioFiles $job): bool => $job->mediaIds === [$media->id] && $job->cvPath === $document->storage_path && $job->afterCommit === true);
});

it('refuses deletion while generation is active', function (string $status) {
    $portfolio = Portfolio::factory()->create();
    Generation::factory()->for($portfolio)->create(['status' => $status]);

    $this->actingAs($portfolio->user)->from(route('userzone.portfolios.show', $portfolio))->delete(route('userzone.portfolios.destroy', $portfolio))
        ->assertRedirect(route('userzone.portfolios.show', $portfolio))
        ->assertSessionHasErrors(['portfolio' => 'Wait for generation to finish before deleting this portfolio.']);
    $this->assertModelExists($portfolio);
})->with(['queued', 'running']);

it('deletes a published portfolio and clears its default pointer', function () {
    $portfolio = Portfolio::factory()->published()->create();
    $owner = $portfolio->user;
    $owner->forceFill(['default_portfolio_id' => $portfolio->id])->save();

    $this->actingAs($owner)->delete(route('userzone.portfolios.destroy', $portfolio))->assertRedirect(route('dashboard'));

    $this->assertModelMissing($portfolio);
    expect($owner->fresh()->default_portfolio_id)->toBeNull();
});
