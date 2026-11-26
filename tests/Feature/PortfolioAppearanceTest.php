<?php

use App\Actions\PublishPortfolio;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('images_local');
    Http::preventStrayRequests();
});

it('saves one appearance setting at a time without changing content, evidence or the published snapshot', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    $snapshot = $portfolio->fresh()->published_snapshot;
    $home = $portfolio->home_content;
    $cv = $portfolio->cv_content;
    $project = $portfolio->projects()->first()->getAttributes();
    $url = route('userzone.appearance.update', $portfolio);

    $this->patchJson($url, ['field' => 'preset', 'value' => 'editorial', 'accent_color' => '#ffffff', 'home_content' => []])->assertOk()->assertJsonPath('appearance.preset', 'editorial')->assertJsonPath('appearance.accent_color', '#ea580c');
    $this->patchJson($url, ['field' => 'accent_color', 'value' => '#12AB34'])->assertOk()->assertJsonPath('appearance.preset', 'editorial')->assertJsonPath('appearance.accent_color', '#12ab34');

    $portfolio->refresh();
    expect($portfolio->home_content)->toBe($home);
    expect($portfolio->cv_content)->toBe($cv);
    expect($portfolio->projects()->first()->getAttributes())->toBe($project);
    expect($portfolio->published_snapshot)->toBe($snapshot);
    Http::assertNothingSent();
});

it('previews and publishes every preset on home, CV and project pages with owner controls kept private', function (string $preset) {
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $this->actingAs($portfolio->user);
    $this->patchJson(route('userzone.appearance.update', $portfolio), ['field' => 'preset', 'value' => $preset])->assertOk();
    $this->patchJson(route('userzone.appearance.update', $portfolio), ['field' => 'accent_color', 'value' => '#ffffff'])->assertOk();

    $this->get(route('userzone.preview.home', $portfolio))->assertOk()->assertSee('data-portfolio-preset="'.$preset.'"', false)->assertSee('Appearance')->assertSee('#ffffff')->assertSee('data-appearance-hex', false);
    $this->get(route('userzone.preview.cv', $portfolio))->assertOk()->assertSee('data-portfolio-preset="'.$preset.'"', false)->assertSee('Appearance')->assertSee('Example Studio');
    $this->get(route('userzone.preview.project', [$portfolio, $project]))->assertOk()->assertSee('data-portfolio-preset="'.$preset.'"', false)->assertSee('Appearance')->assertSee('Technical details');
    $this->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertSessionHasNoErrors();

    $address = [$portfolio->user->username, $portfolio->slug];
    $this->get(route('portfolio.home', $address))->assertOk()->assertSee('data-portfolio-preset="'.$preset.'"', false)->assertSee('#ffffff')->assertDontSee('data-appearance-form', false)->assertDontSee('data-inline-editor', false);
    $this->get(route('portfolio.cv', $address))->assertOk()->assertSee('data-portfolio-preset="'.$preset.'"', false)->assertDontSee('data-appearance-form', false);
    $this->get(route('portfolio.project', [...$address, $project->slug]))->assertOk()->assertSee('data-portfolio-preset="'.$preset.'"', false)->assertDontSee('data-appearance-form', false);
    expect($portfolio->fresh()->published_snapshot['preset'])->toBe($preset);
    expect($portfolio->fresh()->published_snapshot['accent_color'])->toBe('#ffffff');
})->with(['minimal', 'material', 'editorial']);

it('keeps the previous public appearance until draft appearance is republished', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    $address = [$portfolio->user->username, $portfolio->slug];

    $this->patchJson(route('userzone.appearance.update', $portfolio), ['field' => 'preset', 'value' => 'material'])->assertOk();
    $this->patchJson(route('userzone.appearance.update', $portfolio), ['field' => 'accent_color', 'value' => '#000000'])->assertOk();

    $this->get(route('portfolio.home', $address))->assertOk()->assertSee('data-portfolio-preset="minimal"', false)->assertSee('--portfolio-accent: #ea580c', false)->assertDontSee('data-portfolio-preset="material"', false);
    $this->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertSessionHasNoErrors();
    $this->get(route('portfolio.home', $address))->assertOk()->assertSee('data-portfolio-preset="material"', false)->assertSee('--portfolio-accent: #000000', false);
});

it('rejects unsupported settings, presets and malformed colors without changing the draft', function (array $input, string $error) {
    $portfolio = Portfolio::factory()->generated()->create();
    $before = $portfolio->fresh()->getAttributes();

    $this->actingAs($portfolio->user)->patchJson(route('userzone.appearance.update', $portfolio), $input)->assertUnprocessable()->assertJsonValidationErrors($error);

    expect($portfolio->fresh()->getAttributes())->toBe($before);
})->with([
    'unknown setting' => [['field' => 'published_snapshot', 'value' => '#123456'], 'field'],
    'unknown preset' => [['field' => 'preset', 'value' => 'unknown'], 'value'],
    'CSS injection' => [['field' => 'accent_color', 'value' => '#123456;display:none'], 'value'],
    'short hex' => [['field' => 'accent_color', 'value' => '#123'], 'value'],
    'invalid digits' => [['field' => 'accent_color', 'value' => '#gggggg'], 'value'],
    'wrong shape' => [['field' => 'preset', 'value' => ['material']], 'value'],
    'missing data' => [[], 'field'],
]);

it('requires sign in and returns 404 when another owner tries changing appearance', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $input = ['field' => 'preset', 'value' => 'material'];
    $url = route('userzone.appearance.update', $portfolio);
    $this->patch($url, $input)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->patchJson($url, $input)->assertNotFound();

    expect($portfolio->fresh()->preset)->toBe('minimal');
});

it('locks appearance while generation is queued, running or failed', function (string $status) {
    $portfolio = Portfolio::factory()->generated()->create();
    Generation::factory()->for($portfolio)->create(['status' => $status]);
    $this->actingAs($portfolio->user);

    $this->get(route('userzone.preview.home', $portfolio))->assertOk()->assertDontSee('data-appearance-form', false);
    $this->patchJson(route('userzone.appearance.update', $portfolio), ['field' => 'preset', 'value' => 'editorial'])->assertUnprocessable()->assertJsonValidationErrors('portfolio');

    expect($portfolio->fresh()->preset)->toBe('minimal');
})->with(['queued', 'running', 'failed']);
