<?php

use App\Actions\DeletePortfolio;
use App\Actions\EditPortfolioDraft;
use App\Actions\FakePortfolioProvider;
use App\Actions\PortfolioGenerationProvider;
use App\Actions\ProcessGenerationStep;
use App\Actions\ProviderFailure;
use App\Actions\ProviderResult;
use App\Actions\PublishPortfolio;
use App\Actions\StartPortfolioGeneration;
use App\Jobs\GeneratePortfolioStep;
use App\Models\CvDocument;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    $this->app->bind(PortfolioGenerationProvider::class, FakePortfolioProvider::class);
});

function generationInputPortfolio(?User $owner = null): Portfolio
{
    $portfolio = Portfolio::factory()->for($owner ?? User::factory()->create())->create();
    $document = CvDocument::factory()->for($portfolio)->create(['storage_disk' => 'local', 'status' => 'uploaded', 'extracted_content' => null]);
    Storage::disk('local')->put($document->storage_path, file_get_contents(resource_path('fixtures/demo-cv.pdf')));
    Project::factory()->for($portfolio)->create(['readme_content' => str_repeat('A project to help students manage their coursework. ', 15)]);

    return $portfolio;
}

function runGenerationSteps(Generation $generation, int $count = 6): void
{
    for ($step = 0; $step < $count; $step++) {
        app(ProcessGenerationStep::class)->handle($generation->id, $generation->fresh()->run_version);
    }
}

it('generates a complete draft through private checkpoints and permits publication only after success', function () {
    Storage::fake('local');
    Queue::fake([GeneratePortfolioStep::class]);
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user);
    $before = $portfolio->home_content;
    $this->get(route('userzone.generation.confirm', $portfolio))->assertOk()->assertSee('Source PDF');
    $this->post(route('userzone.generation.store', $portfolio), ['confirm' => '1'])->assertSessionHasNoErrors()->assertRedirect();
    $generation = $portfolio->generations()->sole();
    expect($generation->status)->toBe('queued');
    runGenerationSteps($generation, 3);
    expect($portfolio->fresh()->home_content)->toBe($before);
    expect($generation->fresh()->progress['checkpoints'])->toHaveKeys(['extracting', 'project:'.$portfolio->projects()->sole()->id]);
    expect(fn () => app(PublishPortfolio::class)->handle($portfolio))->toThrow(ValidationException::class);
    runGenerationSteps($generation, 3);
    expect($generation->fresh()->status)->toBe('succeeded');
    expect($portfolio->fresh()->home_content['headline'])->toBe('Developer');
    expect($portfolio->fresh()->cvDocument->status)->toBe('ready');
    expect($portfolio->projects()->sole()->overview)->toBe('Offline demo content.');
    app(PublishPortfolio::class)->handle($portfolio);
    $this->get(route('userzone.generation.status', $portfolio))->assertOk()->assertJsonPath('label', 'Ready')->assertJsonMissingPath('input_snapshot');
    Queue::assertPushed(GeneratePortfolioStep::class, fn ($job): bool => $job->generationId === $generation->id && $job->afterCommit === true);
});

it('requires consent and refuses duplicate or successful starts', function () {
    Storage::fake('local');
    Queue::fake([GeneratePortfolioStep::class]);
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user)->post(route('userzone.generation.store', $portfolio))->assertSessionHasErrors('confirm');
    $this->assertDatabaseCount('generations', 0);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    expect(fn () => app(StartPortfolioGeneration::class)->handle($portfolio))->toThrow(ValidationException::class, 'already in progress');
    runGenerationSteps($generation);
    expect(fn () => app(StartPortfolioGeneration::class)->handle($portfolio))->toThrow(ValidationException::class, 'already been generated');
    $this->assertDatabaseCount('generations', 1);
});

it('enforces the seven-day quota across successes and active reservations', function () {
    $this->freezeTime();
    Storage::fake('local');
    Queue::fake([GeneratePortfolioStep::class]);
    $portfolio = generationInputPortfolio();
    $owner = $portfolio->user;
    Generation::factory()->for($owner)->count(2)->succeeded()->create(['completed_at' => now()->subDays(6)]);
    Generation::factory()->for($owner)->create();
    $this->actingAs($owner);
    expect(fn () => app(StartPortfolioGeneration::class)->handle($portfolio))->toThrow(ValidationException::class, 'slots are in use');
    expect(StartPortfolioGeneration::quota($owner)['available'])->toBe(0);
    $this->travel(1)->days();
    expect(StartPortfolioGeneration::quota($owner)['available'])->toBe(2);
    expect(app(StartPortfolioGeneration::class)->handle($portfolio)->status)->toBe('queued');
});

it('does not refund successful quota on deletion', function () {
    Storage::fake('local');
    Queue::fake();
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    runGenerationSteps($generation);
    app(DeletePortfolio::class)->handle($portfolio);
    expect($generation->fresh()->portfolio_id)->toBeNull();
    expect($generation->fresh()->input_snapshot)->toBeNull();
    expect($generation->fresh()->progress)->toBeNull();
    expect(StartPortfolioGeneration::quota($portfolio->user)['available'])->toBe(2);
});

it('reuses completed steps after a permanent project failure and explicit retry', function () {
    Storage::fake('local');
    Queue::fake();
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user);
    $provider = new class extends FakePortfolioProvider
    {
        public int $extractions = 0;

        public bool $fail = true;

        public function extractCv(string $bytes): ProviderResult
        {
            $this->extractions++;

            return parent::extractCv($bytes);
        }

        public function generateProject(array $evidence): ProviderResult
        {
            if ($this->fail) {
                throw new ProviderFailure('invalid_output', 'Invalid result');
            }

            return parent::generateProject($evidence);
        }
    };
    $this->app->instance(PortfolioGenerationProvider::class, $provider);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    runGenerationSteps($generation, 3);
    expect($generation->fresh()->status)->toBe('failed');
    expect(StartPortfolioGeneration::quota($portfolio->user)['available'])->toBe(3);
    expect(fn () => app(EditPortfolioDraft::class)->handle($portfolio, fn () => null))->toThrow(ValidationException::class, 'frozen inputs');
    $provider->fail = false;
    $retried = app(StartPortfolioGeneration::class)->handle($portfolio);
    expect($retried->id)->toBe($generation->id);
    expect($retried->run_version)->toBe(2);
    app(ProcessGenerationStep::class)->handle($generation->id, 1);
    runGenerationSteps($retried);
    expect($retried->fresh()->status)->toBe('succeeded');
    expect($provider->extractions)->toBe(1);
});

it('honors provider retry times and fails after three attempts per step', function () {
    $this->freezeTime();
    Storage::fake('local');
    Queue::fake();
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user);
    $provider = new class extends FakePortfolioProvider
    {
        public int $calls = 0;

        public function extractCv(string $bytes): ProviderResult
        {
            $this->calls++;
            throw new ProviderFailure('provider_retryable', 'Rate limited', now()->addMinutes(5)->timestamp);
        }
    };
    $this->app->instance(PortfolioGenerationProvider::class, $provider);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    runGenerationSteps($generation, 2);
    expect($generation->fresh()->next_run_at->timestamp)->toBe(now()->addMinutes(5)->timestamp);
    runGenerationSteps($generation, 1);
    expect($provider->calls)->toBe(1);
    $this->travel(5)->minutes();
    runGenerationSteps($generation, 1);
    $this->travel(5)->minutes();
    runGenerationSteps($generation, 1);
    expect($provider->calls)->toBe(3);
    expect($generation->fresh()->status)->toBe('failed');
});

it('recovers an expired lease and fences the old worker response', function () {
    $this->freezeTime();
    Storage::fake('local');
    Queue::fake();
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    $generation->update(['status' => 'running', 'lease_token' => '00000000-0000-0000-0000-000000000001', 'lease_expires_at' => now()->subSecond(), 'next_run_at' => null]);
    $this->artisan('generation:recover')->assertSuccessful();
    expect($generation->fresh()->status)->toBe('queued');
    expect($generation->fresh()->lease_token)->toBeNull();
    runGenerationSteps($generation);
    expect($generation->fresh()->status)->toBe('succeeded');
});

it('discards an in-flight result after portfolio deletion', function () {
    Storage::fake('local');
    Queue::fake();
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user);
    $provider = new class($portfolio) extends FakePortfolioProvider
    {
        public function __construct(private Portfolio $portfolio) {}

        public function extractCv(string $bytes): ProviderResult
        {
            app(DeletePortfolio::class)->handle($this->portfolio);

            return parent::extractCv($bytes);
        }
    };
    $this->app->instance(PortfolioGenerationProvider::class, $provider);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    runGenerationSteps($generation, 2);
    expect($generation->fresh()->status)->toBe('failed');
    expect($generation->fresh()->error_code)->toBe('cancelled');
    expect($generation->fresh()->input_snapshot)->toBeNull();
    expect($generation->fresh()->progress)->toBeNull();
    $this->assertModelMissing($portfolio);
});

it('hides generation evidence and controls from other owners and guests', function () {
    $portfolio = Portfolio::factory()->create();
    $this->get(route('userzone.generation.confirm', $portfolio))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('userzone.generation.status', $portfolio))->assertNotFound();
    $this->get(route('userzone.generation.confirm', $portfolio))->assertNotFound();
    $this->post(route('userzone.generation.store', $portfolio), ['confirm' => 1])->assertNotFound();
    $this->assertDatabaseCount('generations', 0);
});

it('discards a response from an expired worker after recovery replaces its lease', function () {
    $this->freezeTime();
    Storage::fake('local');
    Queue::fake();
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user);
    $provider = new class extends FakePortfolioProvider
    {
        public bool $expire = true;

        public Closure $advanceClock;

        public function extractCv(string $bytes): ProviderResult
        {
            if ($this->expire) {
                $this->expire = false;
                ($this->advanceClock)();
                app(Kernel::class)->call('generation:recover');
            }

            return parent::extractCv($bytes);
        }
    };
    $provider->advanceClock = fn () => $this->travel(4)->minutes();
    $this->app->instance(PortfolioGenerationProvider::class, $provider);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    runGenerationSteps($generation, 2);
    expect($generation->fresh()->status)->toBe('queued');
    expect($generation->fresh()->progress['checkpoints'] ?? [])->not->toHaveKey('extracting');
    runGenerationSteps($generation);
    expect($generation->fresh()->status)->toBe('succeeded');
});

it('processes dispatched generation jobs through the database worker', function () {
    Storage::fake('local');
    config(['queue.default' => 'database']);
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    $this->assertDatabaseCount('jobs', 1);
    for ($step = 0; $step < 5; $step++) {
        $this->artisan('queue:work', ['--once' => true, '--tries' => 1, '--timeout' => 120])->assertSuccessful();
    }
    expect($generation->fresh()->status)->toBe('succeeded');
    $this->assertDatabaseCount('jobs', 0);
});

it('rejects insufficient frozen evidence before any paid processing', function () {
    Storage::fake('local');
    Queue::fake();
    $portfolio = generationInputPortfolio();
    $portfolio->projects()->update(['readme_content' => 'Too short']);
    $this->actingAs($portfolio->user);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    runGenerationSteps($generation, 1);
    expect($generation->fresh()->status)->toBe('failed');
    expect($generation->fresh()->error_code)->toBe('readme_insufficient');
    expect($generation->fresh()->progress['checkpoints'] ?? [])->toBe([]);
    expect($generation->fresh()->usage)->toBe([]);
});

it('releases a stale reservation after three crashed processing attempts', function () {
    $this->freezeTime();
    Storage::fake('local');
    Queue::fake();
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    $generation->update(['status' => 'running', 'lease_token' => '00000000-0000-0000-0000-000000000001', 'lease_expires_at' => now()->subMinute(), 'progress' => ['attempts' => ['checking' => 3]]]);
    $this->artisan('generation:recover')->assertSuccessful();
    expect($generation->fresh()->status)->toBe('failed');
    expect(StartPortfolioGeneration::quota($portfolio->user)['available'])->toBe(3);
});

it('releases undelivered reservations after the one-day recovery deadline', function () {
    $this->freezeTime();
    Storage::fake('local');
    Queue::fake();
    $portfolio = generationInputPortfolio();
    $this->actingAs($portfolio->user);
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    $this->travel(25)->hours();
    $this->artisan('generation:recover')->assertSuccessful();
    expect($generation->fresh()->status)->toBe('failed');
    expect($generation->fresh()->error_code)->toBe('recovery_deadline');
    expect(StartPortfolioGeneration::quota($portfolio->user)['available'])->toBe(3);
});

it('gives camomilesson unlimited slots without allowing regeneration of a completed portfolio', function () {
    Storage::fake('local');
    Queue::fake();
    $owner = User::factory()->create(['username' => 'camomilesson']);
    Generation::factory()->for($owner)->count(3)->succeeded()->create(['portfolio_id' => null]);
    $portfolio = generationInputPortfolio($owner);
    $this->actingAs($owner);
    expect(StartPortfolioGeneration::quota($owner)['unlimited'])->toBeTrue();
    expect(StartPortfolioGeneration::quota($owner)['available'])->toBeNull();
    $this->get(route('userzone.generation.confirm', $portfolio))->assertOk()->assertSee('Unlimited generation slots.')->assertDontSee('of 3 generation slots available');
    $generation = app(StartPortfolioGeneration::class)->handle($portfolio);
    expect($generation->status)->toBe('queued');
    runGenerationSteps($generation);
    expect($generation->fresh()->status)->toBe('succeeded');
    expect(fn () => app(StartPortfolioGeneration::class)->handle($portfolio))->toThrow(ValidationException::class, 'already been generated');
    Queue::assertPushed(GeneratePortfolioStep::class);
});
