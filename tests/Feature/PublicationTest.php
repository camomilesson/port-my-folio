<?php

use App\Actions\PublishPortfolio;
use App\Jobs\DeleteUnusedProjectMedia;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('images_local');
});

it('publishes a consistent saved snapshot and automatically selects the first default', function () {
    Queue::fake([DeleteUnusedProjectMedia::class]);
    $portfolio = Portfolio::factory()->generated()->create();

    $this->actingAs($portfolio->user)->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => '1'])->assertRedirect(route('userzone.portfolios.show', $portfolio))->assertSessionHasNoErrors();

    $portfolio->refresh();
    expect($portfolio->isPublished())->toBeTrue();
    expect($portfolio->user->default_portfolio_id)->toBe($portfolio->id);
    expect($portfolio->published_snapshot['cv_content']['name'])->toBe($portfolio->cv_content['name']);
    expect($portfolio->published_snapshot['original_pdf']['path'])->toBe($portfolio->cvDocument->storage_path);
    expect($portfolio->published_snapshot['projects'][0]['id'])->toBe($portfolio->projects->first()->id);
    expect($portfolio->published_snapshot['cv_content'])->not->toHaveKey('allow_original_download');
    Queue::assertPushed(DeleteUnusedProjectMedia::class, fn ($job) => $job->afterCommit === true);
});

it('does not require optional sections, contacts, images or extra prose before publication', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $portfolio->update(['home_content' => null, 'cv_content' => null, 'focus' => null]);
    $portfolio->projects()->update(['title' => null, 'summary' => null, 'overview' => null]);

    $this->actingAs($portfolio->user)->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertSessionHasNoErrors();

    expect($portfolio->fresh()->isPublished())->toBeTrue();
});

it('requires explicit confirmation and successful initial generation without a production fixture bypass', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs($portfolio->user);
    $this->post(route('userzone.publication.publish', $portfolio), [])->assertSessionHasErrors('confirm_publication');
    $portfolio->generations()->update(['status' => 'failed']);
    $this->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertSessionHasErrors('publication');
    $portfolio->generations()->update(['status' => 'succeeded']);
    $this->app->instance('env', 'production');
    $this->withSession(['_token' => 'valid-token'])->post(route('userzone.publication.publish', $portfolio), ['_token' => 'valid-token', 'confirm_publication' => 1])->assertSessionHasErrors('publication');

    expect($portfolio->fresh()->isPublished())->toBeFalse();
});

it('rejects invalid saved content and missing source files without replacing an existing snapshot', function (string $case) {
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs($portfolio->user);
    app(PublishPortfolio::class)->handle($portfolio);
    $snapshot = $portfolio->fresh()->published_snapshot;
    if ($case === 'unsafe link') {
        $portfolio->projects()->update(['live_url' => 'javascript:alert(1)']);
    } elseif ($case === 'missing PDF') {
        Storage::disk('local')->delete($portfolio->cvDocument->storage_path);
    } elseif ($case === 'no projects') {
        $portfolio->projects()->delete();
    } else {
        $portfolio->update(['accent_color' => 'invalid']);
    }

    $this->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertSessionHasErrors();

    expect($portfolio->fresh()->published_snapshot)->toBe($snapshot);
})->with(['unsafe link', 'missing PDF', 'no projects', 'bad appearance']);

it('preserves the default when publishing another portfolio and offers explicit switching', function () {
    $first = Portfolio::factory()->generated()->create();
    $second = Portfolio::factory()->for($first->user)->generated()->create();
    $this->actingAs($first->user);
    $this->post(route('userzone.publication.publish', $first), ['confirm_publication' => 1])->assertSessionHasNoErrors();
    $this->post(route('userzone.publication.publish', $second), ['confirm_publication' => 1])->assertSessionHasNoErrors();
    expect($first->user->fresh()->default_portfolio_id)->toBe($first->id);
    $this->get(route('dashboard'))->assertOk()->assertSee(route('userzone.publication.default', $second));
    $this->get(route('userzone.portfolios.show', $second))->assertOk()->assertSee('Make default');

    $this->post(route('userzone.publication.default', $second))->assertSessionHasNoErrors();
    expect($first->user->fresh()->default_portfolio_id)->toBe($second->id);
});

it('clears the default on unpublish without silently selecting another portfolio', function () {
    $first = Portfolio::factory()->generated()->create();
    $second = Portfolio::factory()->for($first->user)->generated()->create();
    $this->actingAs($first->user);
    app(PublishPortfolio::class)->handle($first);
    app(PublishPortfolio::class)->handle($second);
    Generation::factory()->for($first)->create(['status' => 'running']);

    $this->delete(route('userzone.publication.unpublish', $first))->assertSessionHasNoErrors();

    expect($first->fresh()->isPublished())->toBeFalse();
    expect($first->user->fresh()->default_portfolio_id)->toBeNull();
    expect($second->fresh()->isPublished())->toBeTrue();
    $this->get(route('portfolio.default', $first->user->username))->assertNotFound();
    $this->post(route('userzone.publication.publish', $second), ['confirm_publication' => 1])->assertSessionHasNoErrors();
    expect($first->user->fresh()->default_portfolio_id)->toBe($second->id);
});

it('rejects foreign publication actions and unpublished default selection', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $this->actingAs(User::factory()->create());
    $this->get(route('userzone.publication.confirm', $portfolio))->assertNotFound();
    $this->post(route('userzone.publication.publish', $portfolio), ['confirm_publication' => 1])->assertNotFound();
    $this->post(route('userzone.publication.default', $portfolio))->assertNotFound();
    $this->delete(route('userzone.publication.unpublish', $portfolio))->assertNotFound();
    $this->actingAs($portfolio->user)->post(route('userzone.publication.default', $portfolio))->assertSessionHasErrors('publication');
    $this->get(route('userzone.publication.confirm', $portfolio))->assertOk()->assertSee('original CV PDF will become public');
});
