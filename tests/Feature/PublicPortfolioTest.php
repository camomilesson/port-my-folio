<?php

use App\Actions\PublishPortfolio;
use App\Actions\UnpublishPortfolio;
use App\Jobs\DeletePortfolioFiles;
use App\Jobs\DeleteUnusedProjectMedia;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('images_local');
});

it('serves published home, CV, projects and original PDF anonymously from their snapshot', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $media = $project->addMedia(UploadedFile::fake()->image('sample.png'))->withCustomProperties(['caption' => 'Published caption', 'alt_text' => 'Published description'])->toMediaCollection('gallery');
    $cv = $portfolio->cv_content;
    $cv['allow_original_download'] = false;
    $portfolio->update(['cv_content' => $cv]);
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    Auth::forgetGuards();
    $address = [$portfolio->user->username, $portfolio->slug];

    $this->get(route('portfolio.home', $address))->assertOk()->assertSee($project->title)->assertSee('Explore my CV')->assertDontSee('Private draft preview')->assertDontSee('Back to editor')->assertDontSee('Sign out');
    $this->get(route('portfolio.cv', $address))->assertOk()->assertSee('Example Studio')->assertSee(route('portfolio.pdf', $address));
    $this->get(route('portfolio.project', [...$address, $project->slug]))->assertOk()->assertSee('Published caption')->assertSee('Published description');
    $this->get(route('portfolio.pdf', $address))->assertOk()->assertDownload('cv.pdf')->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('portfolio.image', [...$address, $project->slug, $media->id]))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('portfolio.default', $portfolio->user->username))->assertRedirect(route('portfolio.home', $address));
});

it('isolates draft text, identity, appearance and gallery metadata until republishing', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $media = $project->addMedia(UploadedFile::fake()->image('before.png'))->withCustomProperties(['caption' => 'Original caption', 'alt_text' => 'Original alt'])->toMediaCollection('gallery');
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    $address = [$portfolio->user->username, $portfolio->slug];
    $beforeTitle = $project->title;
    $project->update(['title' => 'Changed draft title']);
    $cv = $portfolio->cv_content;
    $cv['name'] = 'Changed draft name';
    $portfolio->update(['cv_content' => $cv, 'accent_color' => '#123456']);
    $media->setCustomProperty('caption', 'Changed draft caption')->save();

    $this->get(route('portfolio.home', $address))->assertOk()->assertSee($beforeTitle)->assertDontSee('Changed draft title')->assertDontSee('Changed draft name')->assertDontSee('#123456');
    $this->get(route('portfolio.project', [...$address, $project->slug]))->assertOk()->assertSee('Original caption')->assertDontSee('Changed draft caption');
    $this->get(route('userzone.preview.home', $portfolio))->assertOk()->assertSee('Changed draft title')->assertSee('Changed draft name');
    app(PublishPortfolio::class)->handle($portfolio->fresh());
    $this->get(route('portfolio.home', $address))->assertOk()->assertSee('Changed draft title')->assertSee('Changed draft name')->assertSee('#123456');
    $this->get(route('portfolio.project', [...$address, $project->slug]))->assertOk()->assertSee('Changed draft caption');
});

it('retains a removed project and its image until republishing then cleans the old references', function () {
    Queue::fake([DeleteUnusedProjectMedia::class]);
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    Project::factory()->for($portfolio)->create();
    $media = $project->addMedia(UploadedFile::fake()->image('cover.png'))->withCustomProperties(['alt_text' => 'Old cover'])->toMediaCollection('gallery');
    $path = $media->getPathRelativeToRoot();
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    $address = [$portfolio->user->username, $portfolio->slug, $project->slug];

    $this->delete(route('userzone.projects.destroy', [$portfolio, $project]))->assertRedirect();
    (new DeleteUnusedProjectMedia($project->id))->handle();
    $this->assertSoftDeleted($project);
    Storage::disk('images_local')->assertExists($path);
    $this->get(route('portfolio.project', $address))->assertOk()->assertSee($project->title);
    $this->get(route('portfolio.image', [...$address, $media->id]))->assertOk();

    app(PublishPortfolio::class)->handle($portfolio->fresh());
    (new DeleteUnusedProjectMedia($project->id))->handle();
    $this->assertModelMissing($media);
    expect(Project::withTrashed()->find($project->id))->toBeNull();
    Storage::disk('images_local')->assertMissing($path);
    $this->get(route('portfolio.project', $address))->assertNotFound();
    $this->get(route('portfolio.image', [...$address, $media->id]))->assertNotFound();
});

it('revokes all public access on unpublish while preserving the current draft and its source', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $media = $project->addMedia(UploadedFile::fake()->image('retained.png'))->withCustomProperties(['decorative' => true])->toMediaCollection('gallery');
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    $address = [$portfolio->user->username, $portfolio->slug];
    app(UnpublishPortfolio::class)->handle($portfolio);

    foreach ([route('portfolio.home', $address), route('portfolio.cv', $address), route('portfolio.pdf', $address), route('portfolio.project', [...$address, $project->slug]), route('portfolio.image', [...$address, $project->slug, $media->id]), route('portfolio.default', $portfolio->user->username)] as $url) {
        $this->get($url)->assertNotFound();
    }
    $this->get(route('userzone.preview.home', $portfolio))->assertOk();
    $this->get(route('userzone.cv-document.show', $portfolio))->assertOk();
    $this->assertModelExists($media);
});

it('does not reveal draft-only media, projects, other owners addresses or invalid default pointers', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    $project = $portfolio->projects()->first();
    $draftMedia = $project->addMedia(UploadedFile::fake()->image('new-draft.png'))->toMediaCollection('gallery');
    $draftProject = Project::factory()->for($portfolio)->create();
    $address = [$portfolio->user->username, $portfolio->slug];

    $this->get(route('portfolio.image', [...$address, $project->slug, $draftMedia->id]))->assertNotFound();
    $this->get(route('portfolio.project', [...$address, $draftProject->slug]))->assertNotFound();
    $other = User::factory()->create(['default_portfolio_id' => $portfolio->id]);
    $this->get(route('portfolio.default', $other->username))->assertNotFound();
    $this->get(route('portfolio.home', [$other->username, $portfolio->slug]))->assertNotFound();
});

it('shows the same helpful not-found response for missing and unpublished public addresses', function () {
    $portfolio = Portfolio::factory()->create();

    $this->get(route('portfolio.home', [$portfolio->user->username, $portfolio->slug]))->assertNotFound()->assertSee("We couldn't find a public portfolio at this address.", false)->assertSee('Sign in with Google')->assertDontSee($portfolio->title)->assertHeaderMissing('Location');
    $this->get('/nobody/missing')->assertNotFound()->assertSee('Sign in with Google');
    $this->get(route('portfolio.default', $portfolio->user->username))->assertNotFound();
});

it('renders public pages without querying draft projects or gallery models', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    Project::factory()->for($portfolio)->count(4)->create();
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $this->get(route('portfolio.home', [$portfolio->user->username, $portfolio->slug]))->assertOk();
        $queries = DB::getQueryLog();
        expect($queries)->toHaveCount(1);
        expect(strtolower($queries[0]['query']))->not->toContain('"projects"')->not->toContain('"media"')->not->toContain('"cv_documents"');
    } finally {
        DB::disableQueryLog();
    }
});

it('escapes published text and validates external links before exposing a snapshot', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $cv = $portfolio->cv_content;
    $cv['name'] = '<script>name</script>';
    $cv['summary'] = '<img src=x onerror=alert(1)>';
    $portfolio->update(['cv_content' => $cv]);
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    $address = [$portfolio->user->username, $portfolio->slug];

    $this->get(route('portfolio.cv', $address))->assertOk()->assertSee($cv['name'])->assertDontSee($cv['name'], false)->assertSee($cv['summary'])->assertDontSee($cv['summary'], false);
    $cv['contact']['links'] = [['id' => 'link', 'label' => 'Unsafe', 'url' => 'javascript:alert(1)']];
    $portfolio->update(['cv_content' => $cv]);
    $this->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertSessionHasErrors('publication');
    $this->get(route('portfolio.cv', $address))->assertOk()->assertDontSee('javascript:alert(1)');
});

it('cleans a removed gallery image after republish without deleting active media', function () {
    Queue::fake([DeleteUnusedProjectMedia::class]);
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $old = $project->addMedia(UploadedFile::fake()->image('old.png'))->withCustomProperties(['decorative' => true])->toMediaCollection('gallery');
    $active = $project->addMedia(UploadedFile::fake()->image('active.png'))->withCustomProperties(['decorative' => true])->toMediaCollection('gallery');
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    $address = [$portfolio->user->username, $portfolio->slug, $project->slug];

    $this->delete(route('userzone.gallery.destroy', [$portfolio, $project, $old]))->assertRedirect();
    (new DeleteUnusedProjectMedia($project->id))->handle();
    $this->get(route('portfolio.image', [...$address, $old->id]))->assertOk();
    app(PublishPortfolio::class)->handle($portfolio->fresh());
    $job = new DeleteUnusedProjectMedia($project->id);
    $job->handle();
    $job->handle();

    $this->assertModelMissing($old);
    Storage::disk('images_local')->assertExists($active->getPathRelativeToRoot());
    $this->get(route('portfolio.image', [...$address, $old->id]))->assertNotFound();
    $this->get(route('portfolio.image', [...$address, $active->id]))->assertOk();
});

it('removes all public access on portfolio deletion and queues immutable file cleanup', function () {
    Queue::fake([DeletePortfolioFiles::class]);
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $media = $project->addMedia(UploadedFile::fake()->image('published.png'))->withCustomProperties(['decorative' => true])->toMediaCollection('gallery');
    $document = $portfolio->cvDocument;
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    $address = [$portfolio->user->username, $portfolio->slug];
    $generation = $portfolio->generations()->first();

    $this->delete(route('userzone.portfolios.destroy', $portfolio))->assertRedirect(route('dashboard'));

    $this->get(route('portfolio.home', $address))->assertNotFound();
    $this->get(route('portfolio.pdf', $address))->assertNotFound();
    $this->get(route('portfolio.image', [...$address, $project->slug, $media->id]))->assertNotFound();
    expect($portfolio->user->fresh()->default_portfolio_id)->toBeNull();
    expect($generation->fresh()->portfolio_id)->toBeNull();
    expect($generation->fresh()->status)->toBe('succeeded');
    Queue::assertPushed(DeletePortfolioFiles::class, fn ($job) => $job->cvPath === $document->storage_path && $job->mediaIds === [$media->id] && $job->afterCommit === true);
});
