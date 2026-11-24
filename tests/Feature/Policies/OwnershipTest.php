<?php

use App\Models\CvDocument;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('restricts each nested resource to its portfolio owner', function (string $type, string $ability) {
    Storage::fake('images_local');
    $portfolio = Portfolio::factory()->create();
    $project = Project::factory()->for($portfolio)->create();
    $resource = match ($type) {
        'portfolio' => $portfolio,
        'project' => $project,
        'cv' => CvDocument::factory()->for($portfolio)->create(),
        'media' => $project->addMedia(UploadedFile::fake()->image('sample.png'))->toMediaCollection('gallery'),
        'generation' => Generation::factory()->for($portfolio)->create(),
    };
    $other = User::factory()->create();

    expect(Gate::forUser($portfolio->user)->allows($ability, $resource))->toBeTrue();
    $denial = Gate::forUser($other)->inspect($ability, $resource);
    expect($denial->denied())->toBeTrue();
    expect($denial->status())->toBe(404);
})->with([
    ['portfolio', 'view'], ['portfolio', 'update'], ['portfolio', 'delete'],
    ['project', 'view'], ['project', 'update'], ['project', 'delete'],
    ['cv', 'view'], ['cv', 'update'], ['cv', 'delete'],
    ['media', 'view'], ['media', 'update'], ['media', 'delete'],
    ['generation', 'view'],
]);

it('does not authorize private access to removed draft projects or their retained media', function () {
    Storage::fake('images_local');
    $project = Project::factory()->create();
    $owner = $project->portfolio->user;
    $media = $project->addMedia(UploadedFile::fake()->image('sample.png'))->toMediaCollection('gallery');
    $project->delete();

    expect(Gate::forUser($owner)->allows('view', $project))->toBeFalse();
    expect(Gate::forUser($owner)->allows('view', $media->fresh()))->toBeFalse();
});
