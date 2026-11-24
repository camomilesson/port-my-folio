<?php

use App\Jobs\DeletePortfolioFiles;
use App\Models\CvDocument;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('cleans up deleted portfolio files without touching another portfolio and tolerates retries', function () {
    Storage::fake('images_local');
    Storage::fake('local');
    $project = Project::factory()->create();
    $media = $project->addMedia(UploadedFile::fake()->image('sample.png'))->toMediaCollection('gallery');
    $document = CvDocument::factory()->for($project->portfolio)->create();
    Storage::disk('local')->put($document->storage_path, 'fixture');
    $other = Project::factory()->create()->addMedia(UploadedFile::fake()->image('other.png'))->toMediaCollection('gallery');
    $path = $media->getPathRelativeToRoot();
    $project->portfolio->delete();
    $job = new DeletePortfolioFiles([$media->id], 'local', $document->storage_path);

    $job->handle();
    $job->handle();

    $this->assertModelMissing($media);
    Storage::disk('images_local')->assertMissing($path);
    Storage::disk('local')->assertMissing($document->storage_path);
    $this->assertModelExists($other);
    Storage::disk('images_local')->assertExists($other->getPathRelativeToRoot());
});
