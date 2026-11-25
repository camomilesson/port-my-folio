<?php

use App\Models\CvDocument;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('connects portfolio content to its owner and orders projects consistently', function () {
    $portfolio = Portfolio::factory()->create();
    $second = Project::factory()->for($portfolio)->create(['position' => 2]);
    $first = Project::factory()->for($portfolio)->create(['position' => 1]);
    $document = CvDocument::factory()->for($portfolio)->create();
    $generation = Generation::factory()->for($portfolio)->create();

    expect($portfolio->user->portfolios->modelKeys())->toBe([$portfolio->id]);
    expect($portfolio->projects->modelKeys())->toBe([$first->id, $second->id]);
    expect($portfolio->cvDocument->is($document))->toBeTrue();
    expect($generation->user->is($portfolio->user))->toBeTrue();
    expect($portfolio->generations->modelKeys())->toBe([$generation->id]);
});

it('allows the same slug for different owners', function () {
    Portfolio::factory()->create(['slug' => 'backend']);
    Portfolio::factory()->create(['slug' => 'backend']);

    $this->assertDatabaseCount('portfolios', 2);
});

it('rejects duplicate slugs within an owner', function () {
    $portfolio = Portfolio::factory()->create(['slug' => 'backend']);
    Portfolio::factory()->for($portfolio->user)->create(['slug' => 'backend']);
})->throws(UniqueConstraintViolationException::class);

it('rejects duplicate repositories within a portfolio', function () {
    $project = Project::factory()->create();
    Project::factory()->for($project->portfolio)->create(['repository_owner' => $project->repository_owner, 'repository_name' => $project->repository_name]);
})->throws(UniqueConstraintViolationException::class);

it('permits only one source CV per portfolio', function () {
    $document = CvDocument::factory()->create();
    CvDocument::factory()->for($document->portfolio)->create();
})->throws(UniqueConstraintViolationException::class);

it('preserves media when a draft project is soft deleted', function () {
    Storage::fake('images_local');
    $project = Project::factory()->create();
    $media = $project->addMedia(UploadedFile::fake()->image('sample.png'))->toMediaCollection('gallery');
    $path = $media->getPathRelativeToRoot();

    $project->delete();

    $this->assertSoftDeleted($project);
    $this->assertModelExists($media);
    Storage::disk('images_local')->assertExists($path);
    expect($project->portfolio->projects()->count())->toBe(0);
});

it('detaches quota history and clears default pointers when a portfolio is removed', function () {
    $portfolio = Portfolio::factory()->published()->create();
    $owner = $portfolio->user;
    $owner->forceFill(['default_portfolio_id' => $portfolio->id])->save();
    $generation = Generation::factory()->for($portfolio)->succeeded()->create();

    $portfolio->delete();

    expect($generation->fresh()->portfolio_id)->toBeNull();
    expect($generation->fresh()->status)->toBe('succeeded');
    expect($owner->fresh()->default_portfolio_id)->toBeNull();
});
