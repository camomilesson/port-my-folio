<?php

use App\Actions\ManageProjectGallery;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('images_local');
});

it('appends a complete multi-image batch in selection order as processed private WebP files', function () {
    $portfolio = Portfolio::factory()->generated()->published()->create();
    $project = $portfolio->projects()->first();
    $first = $project->addMedia(UploadedFile::fake()->image('cover.png'))->toMediaCollection('gallery');
    $snapshot = $portfolio->published_snapshot;
    $file = UploadedFile::fake()->image('one.png', 120, 60);
    file_put_contents($file->getRealPath(), 'PRIVATE-METADATA', FILE_APPEND);

    $this->actingAs($portfolio->user)->postJson(route('userzone.inline.images', [$portfolio, $project]), ['images' => [$file, UploadedFile::fake()->image('two.jpg', 100, 50)], 'details' => [['caption' => 'First added', 'alt_text' => 'Screenshot', 'decorative' => false], ['caption' => 'Second added', 'decorative' => true]]])->assertOk();

    $gallery = $project->fresh()->activeGallery()->values();
    expect($gallery)->toHaveCount(3);
    expect($gallery[0]->id)->toBe($first->id);
    expect($gallery[1]->getCustomProperty('caption'))->toBe('First added');
    expect($gallery[2]->getCustomProperty('caption'))->toBe('Second added');
    expect($gallery[1]->getCustomProperty('width'))->toBe(120);
    expect($gallery[1]->getCustomProperty('alt_text'))->toBe('Screenshot');
    expect($gallery[2]->getCustomProperty('alt_text'))->toBe('');
    expect($gallery[1]->mime_type)->toBe('image/webp');
    expect(Storage::disk('images_local')->get($gallery[1]->getPathRelativeToRoot()))->not->toContain('PRIVATE-METADATA');
    expect(Storage::disk('images_local')->allFiles())->toHaveCount(3);
    expect($portfolio->fresh()->published_snapshot)->toBe($snapshot);
});

it('rejects the entire batch when a later image or description is invalid', function (string $case) {
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $bad = match ($case) {
        'bad format' => UploadedFile::fake()->image('bad.gif'),
        'malformed content' => UploadedFile::fake()->createWithContent('bad.png', substr(file_get_contents(resource_path('fixtures/demo-project.png')), 0, 40)),
        'too wide' => UploadedFile::fake()->image('bad.png', 12001, 1),
        default => UploadedFile::fake()->image('second.png'),
    };
    $details = [['decorative' => true], ['decorative' => $case !== 'missing description']];
    if ($case === 'missing details') {
        array_pop($details);
    }

    $this->actingAs($portfolio->user)->postJson(route('userzone.inline.images', [$portfolio, $project]), ['images' => [UploadedFile::fake()->image('first.png'), $bad], 'details' => $details])->assertUnprocessable();

    $this->assertDatabaseCount('media', 0);
    expect(Storage::disk('images_local')->allFiles())->toBe([]);
})->with(['bad format', 'malformed content', 'too wide', 'missing description', 'missing details']);

it('retains all existing images when a batch would exceed the ten-image cap', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    for ($index = 0; $index < 9; $index++) {
        $project->addMedia(UploadedFile::fake()->image('existing.png'))->toMediaCollection('gallery');
    }
    $before = Storage::disk('images_local')->allFiles();

    $this->actingAs($portfolio->user)->postJson(route('userzone.inline.images', [$portfolio, $project]), ['images' => [UploadedFile::fake()->image('one.png'), UploadedFile::fake()->image('two.png')], 'details' => [['decorative' => true], ['decorative' => true]]])->assertUnprocessable()->assertJsonValidationErrors('images')->assertJsonPath('errors.images.0', 'A project can contain at most ten gallery images.');

    expect($project->fresh()->activeGallery())->toHaveCount(9);
    expect(Storage::disk('images_local')->allFiles())->toBe($before);
});

it('removes all new files and records when storage fails halfway through a batch', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $existing = $project->addMedia(UploadedFile::fake()->image('cover.png'))->toMediaCollection('gallery');
    $before = Storage::disk('images_local')->allFiles();
    $this->actingAs($portfolio->user);
    $realDisk = Storage::disk('images_local');
    $disk = Mockery::mock($realDisk)->makePartial();
    $writes = 0;
    $disk->shouldReceive('put')->twice()->andReturnUsing(function (string $path, mixed $bytes) use ($realDisk, &$writes): bool {
        $realDisk->put($path, $bytes);
        if (++$writes === 2) {
            throw new RuntimeException('Batch storage interruption.');
        }

        return true;
    });
    Storage::set('images_local', $disk);

    expect(fn () => app(ManageProjectGallery::class)->storeBatch($portfolio, $project, [UploadedFile::fake()->image('one.png'), UploadedFile::fake()->image('two.png')], [['decorative' => true], ['decorative' => true]]))->toThrow(RuntimeException::class, 'Batch storage interruption.');

    $this->assertDatabaseCount('media', 1);
    $this->assertModelExists($existing);
    expect($realDisk->allFiles())->toBe($before);
});

it('edits image metadata without replacing files and can change a decorative image into a described one', function () {
    $portfolio = Portfolio::factory()->generated()->published()->create();
    $project = $portfolio->projects()->first();
    $media = $project->addMedia(UploadedFile::fake()->image('one.png'))->withCustomProperties(['caption' => 'Original caption', 'alt_text' => '', 'decorative' => true])->toMediaCollection('gallery');
    $snapshot = $portfolio->published_snapshot;
    $before = Storage::disk('images_local')->get($media->getPathRelativeToRoot());
    $this->actingAs($portfolio->user);
    $url = route('userzone.inline.image', [$portfolio, $project, $media]);

    $this->patchJson($url, ['field' => 'alt_text', 'value' => 'Project dashboard'])->assertOk();
    $this->patchJson($url, ['field' => 'caption', 'value' => '<script>caption</script>'])->assertOk();

    expect($media->fresh()->getCustomProperty('decorative'))->toBeFalse();
    expect($media->fresh()->getCustomProperty('alt_text'))->toBe('Project dashboard');
    expect($media->fresh()->getCustomProperty('caption'))->toBe('<script>caption</script>');
    expect(Storage::disk('images_local')->get($media->getPathRelativeToRoot()))->toBe($before);
    expect($portfolio->fresh()->published_snapshot)->toBe($snapshot);
    $this->get(route('userzone.preview.project', [$portfolio, $project]))->assertOk()->assertSee('Edit Image caption')->assertSee('<script>caption</script>')->assertDontSee('<script>caption</script>', false);
    $this->patchJson($url, ['field' => 'alt_text', 'value' => ''])->assertUnprocessable()->assertJsonValidationErrors('alt_text');
    $this->patchJson($url, ['field' => 'decorative', 'value' => '1'])->assertOk();
    expect($media->fresh()->getCustomProperty('alt_text'))->toBe('');
});

it('refuses gallery changes during generation and across owner or project boundaries', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $media = $project->addMedia(UploadedFile::fake()->image('one.png'))->withCustomProperties(['decorative' => true])->toMediaCollection('gallery');
    $input = ['images' => [UploadedFile::fake()->image('two.png')], 'details' => [['decorative' => true]]];
    $upload = route('userzone.inline.images', [$portfolio, $project]);
    $metadata = route('userzone.inline.image', [$portfolio, $project, $media]);
    $this->post($upload, $input)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->postJson($upload, $input)->assertNotFound();
    $this->patchJson($metadata, ['field' => 'caption', 'value' => 'Blocked'])->assertNotFound();
    $other = Project::factory()->for($portfolio)->create();
    $this->actingAs($portfolio->user)->patchJson(route('userzone.inline.image', [$portfolio, $other, $media]), ['field' => 'caption', 'value' => 'Blocked'])->assertNotFound();
    Generation::factory()->for($portfolio)->create(['status' => 'running']);
    $this->postJson($upload, $input)->assertUnprocessable()->assertJsonValidationErrors('portfolio');
    $this->patchJson($metadata, ['field' => 'caption', 'value' => 'Blocked'])->assertUnprocessable()->assertJsonValidationErrors('portfolio');

    $this->assertDatabaseCount('media', 1);
    expect($media->fresh()->getCustomProperty('caption'))->toBeNull();
    expect(Storage::disk('images_local')->allFiles())->toHaveCount(1);
});
