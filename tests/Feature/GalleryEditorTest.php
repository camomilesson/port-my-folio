<?php

use App\Jobs\DeleteUnusedProjectMedia;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('images_local');
});

it('processes an image to private WebP, discards the upload and metadata, and serves only the owner', function () {
    $project = Project::factory()->create();
    $portfolio = $project->portfolio;
    $file = UploadedFile::fake()->image('original.png', 3000, 1500);
    file_put_contents($file->getRealPath(), 'PRIVATE-UPLOAD-METADATA', FILE_APPEND);

    $this->actingAs($portfolio->user)->post(route('userzone.gallery.store', [$portfolio, $project]), ['image' => $file, 'decorative' => '1', 'alt_text' => 'ignored'])->assertRedirect()->assertSessionHasNoErrors();

    $media = $project->fresh()->getFirstMedia('gallery');
    expect($media->mime_type)->toBe('image/webp');
    expect($media->getCustomProperty('width'))->toBe(2400);
    expect($media->getCustomProperty('height'))->toBe(1200);
    expect($media->getCustomProperty('alt_text'))->toBe('');
    expect($media->disk)->toBe('images_local');
    $bytes = Storage::disk('images_local')->get($media->getPathRelativeToRoot());
    expect($bytes)->not->toContain('PRIVATE-UPLOAD-METADATA');
    expect(Storage::disk('images_local')->allFiles())->toHaveCount(1);
    $this->get(route('userzone.gallery.show', [$portfolio, $project, $media]))->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs(User::factory()->create())->get(route('userzone.gallery.show', [$portfolio, $project, $media]))->assertNotFound();
    $this->get('/storage/'.$media->getPathRelativeToRoot())->assertForbidden();
});

it('validates upload formats and limits', function (string $case) {
    $project = Project::factory()->create();
    $image = match ($case) {
        'gif' => UploadedFile::fake()->image('bad.gif'),
        'too wide' => UploadedFile::fake()->image('wide.png', 12001, 1),
        'too big' => UploadedFile::fake()->image('large.png')->size(10241),
        'animation' => UploadedFile::fake()->createWithContent('animated.png', substr(file_get_contents(resource_path('fixtures/demo-project.png')), 0, 33).pack('N', 8).'acTL'.pack('NN', 2, 0).pack('N', 0).substr(file_get_contents(resource_path('fixtures/demo-project.png')), 33)),
        default => UploadedFile::fake()->image('normal.png'),
    };
    $input = ['image' => $image, 'decorative' => $case !== 'missing alt'];

    $this->actingAs($project->portfolio->user)->post(route('userzone.gallery.store', [$project->portfolio, $project]), $input)->assertSessionHasErrors();

    $this->assertDatabaseCount('media', 0);
    expect(Storage::disk('images_local')->allFiles())->toBe([]);
})->with(['gif', 'too wide', 'too big', 'animation']);

it('enforces ten active images and rejects mismatched media IDs', function () {
    $project = Project::factory()->create();
    for ($index = 0; $index < 10; $index++) {
        $project->addMedia(UploadedFile::fake()->image('image.png'))->toMediaCollection('gallery');
    }
    $this->actingAs($project->portfolio->user);

    $this->post(route('userzone.gallery.store', [$project->portfolio, $project]), ['image' => UploadedFile::fake()->image('eleventh.png'), 'decorative' => true])->assertSessionHasErrors('image');
    expect($project->fresh()->activeGallery())->toHaveCount(10);
    $other = Project::factory()->for($project->portfolio)->create();
    $media = $project->getFirstMedia('gallery');
    $this->put(route('userzone.gallery.update', [$project->portfolio, $other, $media]), ['decorative' => true])->assertNotFound();
    $this->delete(route('userzone.gallery.destroy', [$project->portfolio, $other, $media]))->assertNotFound();
    $this->post(route('userzone.gallery.move', [$project->portfolio, $other, $media]), ['direction' => 'up'])->assertNotFound();
});

it('updates image metadata and cover order without changing files or published snapshots', function () {
    $portfolio = Portfolio::factory()->published()->create();
    $project = Project::factory()->for($portfolio)->create();
    $first = $project->addMedia(UploadedFile::fake()->image('first.png'))->toMediaCollection('gallery');
    $second = $project->addMedia(UploadedFile::fake()->image('second.png'))->toMediaCollection('gallery');
    $snapshot = $portfolio->published_snapshot;
    $files = Storage::disk('images_local')->allFiles();
    $this->actingAs($portfolio->user);

    $this->put(route('userzone.gallery.update', [$portfolio, $project, $second]), ['caption' => '<script>bad</script>', 'decorative' => false, 'alt_text' => 'Dashboard screenshot'])->assertSessionHasNoErrors();
    $this->post(route('userzone.gallery.move', [$portfolio, $project, $second]), ['direction' => 'up'])->assertSessionHasNoErrors();

    expect($project->fresh()->activeGallery()->first()->id)->toBe($second->id);
    expect($second->fresh()->getCustomProperty('alt_text'))->toBe('Dashboard screenshot');
    expect(Storage::disk('images_local')->allFiles())->toBe($files);
    expect($portfolio->fresh()->published_snapshot)->toBe($snapshot);
    $this->get(route('userzone.preview.project', [$portfolio, $project]))->assertOk()->assertSee('<script>bad</script>')->assertDontSee('<script>bad</script>', false);
});

it('tombstones draft images and retains published files until publication cleanup', function () {
    Queue::fake([DeleteUnusedProjectMedia::class]);
    $portfolio = Portfolio::factory()->published()->create();
    $project = Project::factory()->for($portfolio)->create();
    $media = $project->addMedia(UploadedFile::fake()->image('cover.png'))->toMediaCollection('gallery');
    $path = $media->getPathRelativeToRoot();
    $snapshot = $portfolio->published_snapshot;
    $snapshot['projects'] = [['id' => $project->id, 'gallery' => [['id' => $media->id]]]];
    $portfolio->update(['published_snapshot' => $snapshot]);

    $this->actingAs($portfolio->user)->delete(route('userzone.gallery.destroy', [$portfolio, $project, $media]))->assertRedirect();
    (new DeleteUnusedProjectMedia($project->id))->handle();

    expect($project->fresh()->activeGallery())->toBeEmpty();
    $this->assertModelExists($media);
    Storage::disk('images_local')->assertExists($path);
    $this->get(route('userzone.gallery.show', [$portfolio, $project, $media]))->assertNotFound();
    Queue::assertPushed(DeleteUnusedProjectMedia::class);
});

it('cleans unreferenced tombstones idempotently while preserving active files', function () {
    $project = Project::factory()->create();
    $media = $project->addMedia(UploadedFile::fake()->image('removed.png'))->withCustomProperties(['draft_removed' => true])->toMediaCollection('gallery');
    $active = $project->addMedia(UploadedFile::fake()->image('active.png'))->toMediaCollection('gallery');
    $path = $media->getPathRelativeToRoot();
    $job = new DeleteUnusedProjectMedia($project->id);

    $job->handle();
    $job->handle();

    $this->assertModelMissing($media);
    Storage::disk('images_local')->assertMissing($path);
    Storage::disk('images_local')->assertExists($active->getPathRelativeToRoot());
});

it('accepts each agreed still-image format and preserves aspect ratio', function (string $extension) {
    $project = Project::factory()->create();

    $this->actingAs($project->portfolio->user)->post(route('userzone.gallery.store', [$project->portfolio, $project]), ['image' => UploadedFile::fake()->image('upload.'.$extension, 120, 60), 'decorative' => false, 'alt_text' => 'A screenshot'])->assertSessionHasNoErrors();

    $media = $project->fresh()->getFirstMedia('gallery');
    expect($media->mime_type)->toBe('image/webp');
    expect($media->getCustomProperty('width'))->toBe(120);
    expect($media->getCustomProperty('height'))->toBe(60);
    expect($media->getCustomProperty('alt_text'))->toBe('');
})->with(['jpg', 'png', 'webp']);
