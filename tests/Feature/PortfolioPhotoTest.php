<?php

use App\Actions\BuildPortfolioSnapshot;
use App\Actions\StorePortfolioPhoto;
use App\Jobs\DeletePortfolioFiles;
use App\Jobs\DeleteUnusedPortfolioPhoto;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('images_local');
});

it('stores a processed private photo and shows it only to the portfolio owner before publication', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $original = $portfolio->home_content;
    $file = UploadedFile::fake()->image('portrait.jpg', 120, 160);
    file_put_contents($file->getRealPath(), 'PRIVATE-PHOTO-METADATA', FILE_APPEND);
    $this->actingAs($portfolio->user);

    $this->get(route('userzone.preview.home', $portfolio))->assertOk()->assertSee('Add photo')->assertDontSee('Save photo');

    $this->postJson(route('userzone.photo.store', $portfolio), ['photo' => $file])->assertOk();

    $photo = $portfolio->fresh()->home_content['photo'];
    expect($photo['mime_type'])->toBe('image/webp');
    expect($photo['width'])->toBe(120);
    expect($photo['height'])->toBe(160);
    expect(Storage::disk('images_local')->get($photo['path']))->not->toContain('PRIVATE-PHOTO-METADATA');
    expect(Storage::disk('images_local')->allFiles())->toHaveCount(1);
    expect($portfolio->fresh()->home_content['introduction'])->toBe($original['introduction']);
    expect($portfolio->fresh()->published_snapshot)->toBeNull();
    $this->get(route('userzone.preview.home', $portfolio))->assertOk()->assertSee('Change photo')->assertDontSee('Save photo')->assertSee(route('userzone.photo.show', [$portfolio, $photo['id']]));
    $this->get(route('userzone.photo.show', [$portfolio, $photo['id']]))->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'no-store, private');
    $this->get(route('portfolio.photo', [$portfolio->user->username, $portfolio->slug, $photo['id']]))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('userzone.photo.show', [$portfolio, $photo['id']]))->assertNotFound();
});

it('keeps the published photo available until replacements are published and cleans old files afterwards', function () {
    Queue::fake([DeleteUnusedPortfolioPhoto::class]);
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs($portfolio->user);
    $this->postJson(route('userzone.photo.store', $portfolio), ['photo' => UploadedFile::fake()->image('first.png')])->assertOk();
    $first = $portfolio->fresh()->home_content['photo'];
    $this->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertSessionHasNoErrors();
    $this->postJson(route('userzone.photo.store', $portfolio), ['photo' => UploadedFile::fake()->image('second.png')])->assertOk();
    $second = $portfolio->fresh()->home_content['photo'];

    (new DeleteUnusedPortfolioPhoto($portfolio->id, $first))->handle();

    Storage::disk('images_local')->assertExists($first['path']);
    Storage::disk('images_local')->assertExists($second['path']);
    $this->get(route('portfolio.photo', [$portfolio->user->username, $portfolio->slug, $first['id']]))->assertOk();
    $this->get(route('portfolio.photo', [$portfolio->user->username, $portfolio->slug, $second['id']]))->assertNotFound();
    $this->get(route('portfolio.home', [$portfolio->user->username, $portfolio->slug]))->assertOk()->assertDontSee('Change photo')->assertSee(route('portfolio.photo', [$portfolio->user->username, $portfolio->slug, $first['id']]));
    $this->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertSessionHasNoErrors();
    (new DeleteUnusedPortfolioPhoto($portfolio->id, $first))->handle();
    (new DeleteUnusedPortfolioPhoto($portfolio->id, $first))->handle();
    Storage::disk('images_local')->assertMissing($first['path']);
    Storage::disk('images_local')->assertExists($second['path']);
    $this->get(route('portfolio.photo', [$portfolio->user->username, $portfolio->slug, $first['id']]))->assertNotFound();
    Queue::assertPushed(DeleteUnusedPortfolioPhoto::class);
});

it('keeps photos across supporting home saves and includes draft and published photos in deletion cleanup', function () {
    Queue::fake([DeletePortfolioFiles::class, DeleteUnusedPortfolioPhoto::class]);
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs($portfolio->user);
    $this->postJson(route('userzone.photo.store', $portfolio), ['photo' => UploadedFile::fake()->image('first.png')])->assertOk();
    $first = $portfolio->fresh()->home_content['photo'];
    $portfolio->update(['published_snapshot' => app(BuildPortfolioSnapshot::class)->build($portfolio->fresh()), 'published_at' => now()]);
    $this->postJson(route('userzone.photo.store', $portfolio), ['photo' => UploadedFile::fake()->image('second.png')])->assertOk();
    $second = $portfolio->fresh()->home_content['photo'];

    $this->put(route('userzone.content.home.update', $portfolio), ['title' => 'Changed', 'name' => 'Sam', 'headline' => 'Developer', 'introduction' => 'Changed intro'])->assertSessionHasNoErrors();

    expect($portfolio->fresh()->home_content['photo'])->toBe($second);
    $this->delete(route('userzone.portfolios.destroy', $portfolio))->assertRedirect();
    Queue::assertPushed(DeletePortfolioFiles::class, fn (DeletePortfolioFiles $job): bool => count($job->photos) === 2 && $job->photos[0]['id'] === $second['id'] && $job->photos[1]['id'] === $first['id']);
    Queue::assertPushed(DeleteUnusedPortfolioPhoto::class);
    $job = Queue::pushed(DeletePortfolioFiles::class)->first();
    $job->handle();
    Storage::disk('images_local')->assertMissing($first['path']);
    Storage::disk('images_local')->assertMissing($second['path']);
});

it('rejects invalid photos and retains the current photo and all existing files', function (string $case) {
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs($portfolio->user);
    $this->postJson(route('userzone.photo.store', $portfolio), ['photo' => UploadedFile::fake()->image('existing.png')])->assertOk();
    $before = $portfolio->fresh()->home_content;
    $files = Storage::disk('images_local')->allFiles();
    $file = match ($case) {
        'format' => UploadedFile::fake()->image('bad.gif'),
        'dimensions' => UploadedFile::fake()->image('wide.png', 12001, 1),
        default => UploadedFile::fake()->image('big.png')->size(10241),
    };

    $this->postJson(route('userzone.photo.store', $portfolio), ['photo' => $file])->assertUnprocessable();

    expect($portfolio->fresh()->home_content)->toBe($before);
    expect(Storage::disk('images_local')->allFiles())->toBe($files);
})->with(['format', 'dimensions', 'size']);

it('requires sign in, ownership and successful generation for photo changes', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $url = route('userzone.photo.store', $portfolio);
    $input = ['photo' => UploadedFile::fake()->image('portrait.png')];
    $this->post($url, $input)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->postJson($url, $input)->assertNotFound();
    Generation::factory()->for($portfolio)->create(['status' => 'running']);
    $this->actingAs($portfolio->user)->postJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('portfolio');

    expect($portfolio->fresh()->home_content)->not->toHaveKey('photo');
    expect(Storage::disk('images_local')->allFiles())->toBe([]);
});

it('removes a partially written photo without changing the draft when storage fails', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $before = $portfolio->home_content;
    $this->actingAs($portfolio->user);
    $realDisk = Storage::disk('images_local');
    $disk = Mockery::mock($realDisk)->makePartial();
    $disk->shouldReceive('put')->once()->andReturnUsing(function (string $path, mixed $bytes) use ($realDisk): never {
        $realDisk->put($path, $bytes);
        throw new RuntimeException('Photo storage interruption.');
    });
    Storage::set('images_local', $disk);

    expect(fn () => app(StorePortfolioPhoto::class)->handle($portfolio, UploadedFile::fake()->image('portrait.png')))->toThrow(RuntimeException::class, 'Photo storage interruption.');

    expect($portfolio->fresh()->home_content)->toBe($before);
    expect($realDisk->allFiles())->toBe([]);
});

it('cleans the formerly published photo after unpublishing while preserving the current draft photo', function () {
    Queue::fake([DeleteUnusedPortfolioPhoto::class]);
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs($portfolio->user);
    $this->postJson(route('userzone.photo.store', $portfolio), ['photo' => UploadedFile::fake()->image('first.png')])->assertOk();
    $first = $portfolio->fresh()->home_content['photo'];
    $this->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertSessionHasNoErrors();
    $this->postJson(route('userzone.photo.store', $portfolio), ['photo' => UploadedFile::fake()->image('second.png')])->assertOk();
    $second = $portfolio->fresh()->home_content['photo'];

    $this->delete(route('userzone.publication.unpublish', $portfolio))->assertRedirect();
    (new DeleteUnusedPortfolioPhoto($portfolio->id, $first))->handle();

    Storage::disk('images_local')->assertMissing($first['path']);
    Storage::disk('images_local')->assertExists($second['path']);
    $this->get(route('portfolio.photo', [$portfolio->user->username, $portfolio->slug, $first['id']]))->assertNotFound();
    Queue::assertPushed(DeleteUnusedPortfolioPhoto::class);
});

it('refuses publication when the saved photo file is unavailable', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs($portfolio->user);
    $this->postJson(route('userzone.photo.store', $portfolio), ['photo' => UploadedFile::fake()->image('portrait.png')])->assertOk();
    $photo = $portfolio->fresh()->home_content['photo'];
    Storage::disk('images_local')->delete($photo['path']);

    $this->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertSessionHasErrors('publication');

    expect($portfolio->fresh()->published_snapshot)->toBeNull();
});
