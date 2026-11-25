<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Http::preventStrayRequests();
});

function portfolioInputPayload(): array
{
    return ['title' => 'Backend developer', 'slug' => 'backend', 'focus' => '',
        'pdf' => UploadedFile::fake()->createWithContent('cv.pdf', file_get_contents(resource_path('fixtures/demo-cv.pdf'))),
        'repositories' => [['repository_url' => 'https://github.com/example/course', 'author_role' => 'Backend developer in a team of three']]];
}

it('collects PDF and complete repository evidence without calling AI or publishing', function () {
    Storage::fake('local');
    Http::fake(['https://api.github.com/repos/example/course' => Http::response(['private' => false]),
        'https://api.github.com/repos/example/course/readme' => Http::response(str_repeat('A project for students to track assignments. ', 20))]);
    $owner = User::factory()->create();
    $this->actingAs($owner)->get(route('userzone.portfolios.create'))->assertOk()->assertSee('CV PDF')->assertSee('Generate my portfolio');
    $this->post(route('userzone.portfolios.store'), portfolioInputPayload())->assertSessionHasNoErrors()->assertRedirect();
    $portfolio = $owner->portfolios()->sole();
    expect($portfolio->published_snapshot)->toBeNull();
    expect($portfolio->cvDocument->status)->toBe('uploaded');
    expect($portfolio->projects()->sole()->readme_content)->toContain('track assignments');
    expect($portfolio->projects()->sole()->author_role)->toBe('Backend developer in a team of three');
    Storage::disk('local')->assertExists($portfolio->cvDocument->storage_path);
    $this->assertDatabaseCount('generations', 0);
    Http::assertSentCount(2);
});

it('saves no portfolio or PDF when a repository fails preflight', function () {
    Storage::fake('local');
    Http::fake(['https://api.github.com/repos/example/course' => Http::response(['private' => false]),
        'https://api.github.com/repos/example/course/readme' => Http::response('Too short')]);
    $this->actingAs(User::factory()->create())->post(route('userzone.portfolios.store'), portfolioInputPayload())
        ->assertSessionHasErrors('repositories.0.repository_url');
    $this->assertDatabaseCount('portfolios', 0);
    $this->assertDatabaseCount('cv_documents', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects missing PDF and repository roles before external requests', function () {
    Http::fake();
    $this->actingAs(User::factory()->create())->post(route('userzone.portfolios.store'), ['title' => 'Backend', 'slug' => 'backend', 'repositories' => [['repository_url' => 'https://github.com/example/course']]])
        ->assertSessionHasErrors(['pdf', 'repositories.0.author_role']);
    $this->assertDatabaseCount('portfolios', 0);
    Http::assertNothingSent();
});

it('rejects duplicate normalized repositories without saving a partial draft', function () {
    Storage::fake('local');
    Http::fake(['https://api.github.com/repos/example/course' => Http::response(['private' => false]),
        'https://api.github.com/repos/example/course/readme' => Http::response(str_repeat('Project details. ', 30))]);
    $payload = portfolioInputPayload();
    $payload['repositories'][] = ['repository_url' => 'https://github.com/Example/Course.git', 'author_role' => 'Developer'];
    $this->actingAs(User::factory()->create())->post(route('userzone.portfolios.store'), $payload)->assertSessionHasErrors('repositories.1.repository_url');
    $this->assertDatabaseCount('portfolios', 0);
    Http::assertSentCount(2);
});

it('refuses a sixth repository before preflight', function () {
    Http::fake();
    $payload = portfolioInputPayload();
    $payload['repositories'] = array_fill(0, 6, $payload['repositories'][0]);
    $this->actingAs(User::factory()->create())->post(route('userzone.portfolios.store'), $payload)->assertSessionHasErrors('repositories');
    Http::assertNothingSent();
});
