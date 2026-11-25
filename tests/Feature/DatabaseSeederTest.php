<?php

use App\Models\CvDocument;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

it('seeds a usable offline demo with independent owners and stored fixtures', function () {
    Storage::fake('local');
    Storage::fake('images_local');

    $this->seed();

    $owner = User::where('email', 'admin@example.com')->firstOrFail();
    expect($owner->portfolios()->count())->toBe(3);
    expect($owner->defaultPortfolio->isPublished())->toBeTrue();
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('projects', 4);
    $this->assertDatabaseCount('generations', 2);
    foreach (CvDocument::all() as $document) {
        Storage::disk('local')->assertExists($document->storage_path);
    }
    foreach (Media::all() as $media) {
        Storage::disk('images_local')->assertExists($media->getPathRelativeToRoot());
    }
});

it('refuses to seed demo identities in production', function () {
    $this->app->instance('env', 'production');

    expect(fn () => app(DatabaseSeeder::class)->run())->toThrow(LogicException::class, 'Demo data may only be seeded locally or in tests.');
    $this->assertDatabaseCount('users', 0);
});
