<?php

use App\Actions\ManageProjectGallery;
use App\Actions\StorePortfolioCv;
use App\Jobs\DeleteUnusedProjectMedia;
use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('keeps a media tombstone retryable when storage deletion fails', function () {
    Storage::fake('images_local');
    $project = Project::factory()->create();
    $media = $project->addMedia(UploadedFile::fake()->image('removed.png'))->withCustomProperties(['draft_removed' => true])->toMediaCollection('gallery');
    $disk = Storage::disk('images_local');
    $failingDisk = Mockery::mock($disk)->makePartial();
    $failingDisk->shouldReceive('delete')->once()->with($media->getPathRelativeToRoot())->andReturnFalse();
    Storage::set('images_local', $failingDisk);

    expect(fn () => (new DeleteUnusedProjectMedia($project->id))->handle())->toThrow(RuntimeException::class);

    $this->assertModelExists($media);
    Storage::set('images_local', $disk);
    (new DeleteUnusedProjectMedia($project->id))->handle();
    $this->assertModelMissing($media);
});

it('does not save source evidence when a file cannot be stored', function () {
    Storage::fake('local');
    $portfolio = Portfolio::factory()->create();
    $this->actingAs($portfolio->user);
    $disk = Mockery::mock(Storage::disk('local'))->makePartial();
    $disk->shouldReceive('put')->once()->andReturnFalse();
    Storage::set('local', $disk);
    $file = UploadedFile::fake()->createWithContent('cv.pdf', file_get_contents(resource_path('fixtures/demo-cv.pdf')));

    expect(fn () => app(StorePortfolioCv::class)->handle($portfolio, $file))->toThrow(RuntimeException::class);

    $this->assertDatabaseCount('cv_documents', 0);
});

it('rolls back media records and removes a partially written image when storage fails', function () {
    Storage::fake('images_local');
    $project = Project::factory()->create();
    $this->actingAs($project->portfolio->user);
    $realDisk = Storage::disk('images_local');
    $disk = Mockery::mock($realDisk)->makePartial();
    $disk->shouldReceive('put')->once()->andReturnUsing(function (string $path, mixed $bytes) use ($realDisk): never {
        $realDisk->put($path, $bytes);
        throw new RuntimeException('Simulated storage interruption.');
    });
    Storage::set('images_local', $disk);

    expect(fn () => app(ManageProjectGallery::class)->store($project->portfolio, $project, UploadedFile::fake()->image('upload.png'), ['decorative' => true]))->toThrow(RuntimeException::class, 'Simulated storage interruption.');

    $this->assertDatabaseCount('media', 0);
    expect($realDisk->allFiles())->toBe([]);
});
