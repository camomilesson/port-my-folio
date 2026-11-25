<?php

use App\Actions\FakePortfolioProvider;
use App\Actions\GenerationContent;
use App\Actions\PortfolioGenerationProvider;
use App\Actions\ProcessGenerationStep;
use App\Actions\ProviderFailure;
use App\Actions\PublishPortfolio;
use App\Actions\StartPortfolioGeneration;
use App\Jobs\GeneratePortfolioStep;
use App\Models\CvDocument;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['cache.default' => 'array']);
    $this->app->bind(PortfolioGenerationProvider::class, FakePortfolioProvider::class);
});

it('starts from minimal inputs with generated identity and no project role', function () {
    Queue::fake();
    Storage::fake('local');
    $readme = str_repeat('This web application processes requests and stores results in a database. ', 10);
    Http::fake(['https://api.github.com/repos/camomilesson/website-to-conjugate-verbs' => Http::response(['private' => false]),
        'https://api.github.com/repos/camomilesson/website-to-conjugate-verbs/readme' => Http::response($readme)]);
    $owner = User::factory()->create();
    $this->actingAs($owner)->get(route('userzone.portfolios.create'))->assertOk()->assertSee('Generate my portfolio')->assertDontSee('Portfolio title')->assertDontSee('Your role');
    $response = $this->post(route('userzone.portfolios.generate'), [
        'confirm' => '1', 'pdf' => UploadedFile::fake()->createWithContent('cv.pdf', file_get_contents(resource_path('fixtures/demo-cv.pdf'))),
        'repositories' => [['repository_url' => 'https://github.com/camomilesson/website-to-conjugate-verbs']],
    ]);
    $portfolio = $owner->portfolios()->sole();
    $response->assertSessionHasNoErrors()->assertRedirect(route('userzone.preview.home', $portfolio));
    expect($portfolio->title)->toBe($owner->name.' portfolio');
    expect($portfolio->slug)->toStartWith('portfolio-');
    expect($portfolio->generations()->sole()->input_snapshot['projects'][0])->not->toHaveKey('author_role');
    expect($portfolio->generations()->sole()->status)->toBe('queued');
    expect($portfolio->isPublished())->toBeFalse();
    Queue::assertPushed(GeneratePortfolioStep::class);
    Http::assertNothingSent();
    $generation = $portfolio->generations()->sole();
    for ($step = 0; $step < 5; $step++) {
        app(ProcessGenerationStep::class)->handle($generation->id, 1);
    }
    expect($generation->fresh()->status)->toBe('succeeded');
    expect($portfolio->projects()->sole()->readme_content)->toBe($readme);
    expect($portfolio->projects()->sole()->readme_fetched_at)->not->toBeNull();
    expect($portfolio->projects()->sole()->technical_details)->toContain('The server validates');
    Http::assertSentCount(2);
});

it('starts the primary flow after more than three successful generations', function () {
    Queue::fake();
    Storage::fake('local');
    $owner = User::factory()->create();
    Generation::factory()->for($owner)->count(3)->succeeded()->create(['portfolio_id' => null]);
    Http::fake(['https://api.github.com/repos/example/course' => Http::response(['private' => false]), 'https://api.github.com/repos/example/course/readme' => Http::response(str_repeat('Technical project description. ', 20))]);
    $this->actingAs($owner)->post(route('userzone.portfolios.generate'), ['confirm' => 1, 'pdf' => UploadedFile::fake()->createWithContent('cv.pdf', file_get_contents(resource_path('fixtures/demo-cv.pdf'))), 'repositories' => [['repository_url' => 'https://github.com/example/course']]])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('portfolios', 1);
    $this->assertDatabaseCount('cv_documents', 1);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
    Queue::assertPushed(GeneratePortfolioStep::class);
});

it('reveals private checkpoints while leaving the saved draft and public publication unchanged', function () {
    Queue::fake();
    Storage::fake('local');
    $portfolio = Portfolio::factory()->create(['home_content' => ['name' => 'Old draft identity', 'headline' => 'Old headline', 'introduction' => 'Old saved introduction']]);
    $document = CvDocument::factory()->for($portfolio)->create(['storage_disk' => 'local']);
    Storage::disk('local')->put($document->storage_path, file_get_contents(resource_path('fixtures/demo-cv.pdf')));
    $project = Project::factory()->for($portfolio)->create(['readme_content' => str_repeat('A web application with a database and server-rendered templates. ', 10)]);
    $this->actingAs($portfolio->user);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    $home = route('userzone.preview.home', $portfolio);
    $cv = route('userzone.preview.cv', $portfolio);
    $this->get($home)->assertOk()->assertSee('Building your portfolio')->assertDontSee('Old draft identity')->assertSee('Waiting for project content');
    $this->get($cv)->assertOk()->assertSee('Your CV is on its way');
    $processor = app(ProcessGenerationStep::class);
    $processor->handle($generation->id, 1);
    $processor->handle($generation->id, 1);
    $this->get($home)->assertOk()->assertSee('Sam Example')->assertSee('Your introduction will appear here.');
    $this->get($cv)->assertOk()->assertSee('Sam Example')->assertDontSee('Your CV is on its way');
    $processor->handle($generation->id, 1);
    $this->get(route('userzone.preview.project', [$portfolio, $project]))->assertOk()->assertSee('Technical details')->assertSee('The server validates incoming data');
    $this->get($home)->assertOk()->assertSee('A fictional project.')->assertDontSee('Waiting for project content');
    expect($portfolio->fresh()->home_content['introduction'])->toBe('Old saved introduction');
    $this->get(route('portfolio.home', [$portfolio->user->username, $portfolio->slug]))->assertNotFound();
    $generation->refresh()->update(['status' => 'failed', 'error_code' => 'provider_retryable']);
    $this->get($home)->assertOk()->assertSee('Retry remaining steps')->assertSee('A fictional project.');
    $this->post(route('userzone.generation.store', $portfolio), ['confirm' => 1])->assertRedirect($home);
    $generation->refresh();
    for ($step = 0; $step < 2; $step++) {
        $processor->handle($generation->id, $generation->run_version);
    }
    expect($generation->fresh()->status)->toBe('succeeded');
    $this->get($home)->assertOk()->assertSee('Ready')->assertDontSee('Building your portfolio');
    app(PublishPortfolio::class)->handle($portfolio);
    $this->get(route('portfolio.project', [$portfolio->user->username, $portfolio->slug, $project->slug]))->assertOk()->assertSee('The server validates incoming data')->assertDontSee('data-live-portfolio', false);
});

it('does not reveal checkpoint content through guessed owner preview URLs', function () {
    $portfolio = Portfolio::factory()->create();
    $this->actingAs(User::factory()->create())->get(route('userzone.preview.home', $portfolio))->assertNotFound();
    $this->get(route('userzone.preview.cv', $portfolio))->assertNotFound();
});

it('requires substantive technical details in generated project output', function () {
    $project = (new FakePortfolioProvider)->generateProject(['project' => ['repository_owner' => 'example', 'repository_name' => 'course']])->content;
    $project['technical_details'] = '';
    expect(fn () => GenerationContent::validateProject($project))->toThrow(ProviderFailure::class);
});
