<?php

use App\Actions\BuildPortfolioSnapshot;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
});

it('saves one home field and shared identity without rewriting other draft or published data', function () {
    $portfolio = Portfolio::factory()->generated()->published()->create();
    $snapshot = $portfolio->published_snapshot;
    $cv = $portfolio->cv_content;
    $intro = $portfolio->home_content['introduction'];
    $project = $portfolio->projects()->first()->getAttributes();

    $this->actingAs($portfolio->user)->patchJson(route('userzone.inline.page', [$portfolio, 'home']), ['field' => 'name', 'value' => 'New Name', 'published_snapshot' => [], 'slug' => 'ignored'])->assertOk()->assertJsonPath('message', 'Draft saved. Publish changes to update the public version.');

    $portfolio->refresh();
    expect($portfolio->home_content['name'])->toBe('New Name');
    expect($portfolio->cv_content)->toBe(array_replace($cv, ['name' => 'New Name', 'contact' => array_replace(['email' => '', 'phone' => '', 'location' => '', 'links' => []], $cv['contact'])]));
    expect($portfolio->home_content['introduction'])->toBe($intro);
    expect($portfolio->published_snapshot)->toBe($snapshot);
    expect($portfolio->projects()->first()->getAttributes())->toBe($project);
    Http::assertNothingSent();
});

it('merges successive CV changes by stable entry ID and keeps entry order and other fields', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $cv = $portfolio->cv_content;
    $cv['experience'][] = ['id' => 'second-entry', 'organization' => 'Second Studio', 'role' => 'Engineer', 'current' => false, 'start_date' => '2020', 'end_date' => '2022'];
    $portfolio->update(['cv_content' => $cv]);
    $this->actingAs($portfolio->user);
    $url = route('userzone.inline.page', [$portfolio, 'cv']);

    $this->patchJson($url, ['section' => 'experience', 'entry_id' => 'second-entry', 'field' => 'role', 'value' => 'Lead engineer'])->assertOk();
    $this->patchJson($url, ['section' => 'experience', 'entry_id' => 'demo-experience', 'field' => 'description', 'value' => 'Updated description'])->assertOk();
    $this->patchJson($url, ['field' => 'headline', 'value' => 'Platform engineer'])->assertOk();

    $portfolio->refresh();
    expect(array_column($portfolio->cv_content['experience'], 'id'))->toBe(['demo-experience', 'second-entry']);
    expect($portfolio->cv_content['experience'][0]['description'])->toBe('Updated description');
    expect($portfolio->cv_content['experience'][1])->toBe(array_replace($cv['experience'][1], ['role' => 'Lead engineer']));
    expect($portfolio->home_content['headline'])->toBe('Platform engineer');
    expect($portfolio->home_content['introduction'])->toBe('Fictional content for the local demo.');
});

it('clears an end date when an entry becomes current and accepts comma separated skills', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $cv = $portfolio->cv_content;
    $cv['experience'][0]['current'] = false;
    $cv['experience'][0]['end_date'] = '2025';
    $portfolio->update(['cv_content' => $cv]);
    $this->actingAs($portfolio->user);
    $url = route('userzone.inline.page', [$portfolio, 'cv']);

    $this->patchJson($url, ['section' => 'experience', 'entry_id' => 'demo-experience', 'field' => 'current', 'value' => '1'])->assertOk();
    $this->patchJson($url, ['section' => 'skills', 'entry_id' => 'demo-skills', 'field' => 'items', 'value' => ' PHP, Laravel, PHP, PostgreSQL, '])->assertOk();

    expect($portfolio->fresh()->cv_content['experience'][0]['current'])->toBeTrue();
    expect($portfolio->fresh()->cv_content['experience'][0]['end_date'])->toBeNull();
    expect($portfolio->fresh()->cv_content['skills'][0]['items'])->toBe(['PHP', 'Laravel', 'PostgreSQL']);
});

it('returns 422 for invalid or structural CV edits and retains the saved draft', function (array $input, string $error) {
    $portfolio = Portfolio::factory()->generated()->create();
    $before = $portfolio->fresh()->getAttributes();

    $this->actingAs($portfolio->user)->patchJson(route('userzone.inline.page', [$portfolio, 'cv']), $input)->assertUnprocessable()->assertJsonValidationErrors($error);

    expect($portfolio->fresh()->getAttributes())->toBe($before);
})->with([
    'unknown field' => [['field' => 'schema_version', 'value' => 2], 'field'],
    'missing entry' => [['section' => 'experience', 'entry_id' => 'missing', 'field' => 'role', 'value' => 'New'], 'entry_id'],
    'invalid email' => [['section' => 'contact', 'field' => 'email', 'value' => 'invalid'], 'contact.email'],
    'invalid date' => [['section' => 'experience', 'entry_id' => 'demo-experience', 'field' => 'start_date', 'value' => '2024-13'], 'experience.0.start_date'],
    'current with end' => [['section' => 'experience', 'entry_id' => 'demo-experience', 'field' => 'end_date', 'value' => '2025'], 'experience.0.end_date'],
    'empty name' => [['field' => 'name', 'value' => ''], 'name'],
]);

it('rejects unsafe existing links without changing the draft', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $cv = $portfolio->cv_content;
    $cv['contact']['links'] = [['id' => 'website', 'label' => 'Website', 'url' => 'https://example.com']];
    $portfolio->update(['cv_content' => $cv]);

    $this->actingAs($portfolio->user)->patchJson(route('userzone.inline.page', [$portfolio, 'cv']), ['section' => 'links', 'entry_id' => 'website', 'field' => 'url', 'value' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('contact.links.0.url');

    expect($portfolio->fresh()->cv_content)->toBe($cv);
});

it('saves project prose and tags while preserving repository identity and the published snapshot', function () {
    $portfolio = Portfolio::factory()->generated()->published()->create();
    $project = $portfolio->projects()->first();
    $before = $project->getAttributes();
    $snapshot = $portfolio->published_snapshot;
    $this->actingAs($portfolio->user);
    $url = route('userzone.inline.project', [$portfolio, $project]);

    $this->patchJson($url, ['field' => 'technical_details', 'value' => '<script>escaped</script>'])->assertOk();
    $this->patchJson($url, ['field' => 'technologies', 'value' => ' PHP, Laravel, PHP, PostgreSQL, '])->assertOk();

    expect($project->fresh()->technologies)->toBe(['PHP', 'Laravel', 'PostgreSQL']);
    expect($project->fresh()->repository_owner)->toBe($before['repository_owner']);
    expect($project->fresh()->readme_content)->toBe($before['readme_content']);
    expect($portfolio->fresh()->published_snapshot)->toBe($snapshot);
    $this->get(route('userzone.preview.project', [$portfolio, $project]))->assertOk()->assertSee('<script>escaped</script>')->assertDontSee('<script>escaped</script>', false)->assertSee('Edit Technical details');
});

it('returns 422 for unsupported project fields and invalid content', function (string $field, mixed $value) {
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $before = $project->getAttributes();

    $this->actingAs($portfolio->user)->patchJson(route('userzone.inline.project', [$portfolio, $project]), ['field' => $field, 'value' => $value])->assertUnprocessable()->assertJsonValidationErrors($field === 'slug' ? 'field' : 'value');

    expect($project->fresh()->getAttributes())->toBe($before);
})->with(['identity' => ['slug', 'new-slug'], 'required technical details' => ['technical_details', ''], 'too many tags' => ['technologies', implode(',', range(1, 31))], 'wrong type' => ['summary', ['bad']]]);

it('requires the latest generation to succeed before displaying or accepting pencils', function (string $status) {
    $portfolio = Portfolio::factory()->generated()->create();
    Generation::factory()->for($portfolio)->create(['status' => $status]);
    $this->actingAs($portfolio->user);

    $this->get(route('userzone.preview.home', $portfolio))->assertOk()->assertDontSee('data-inline-editor', false);
    $this->patchJson(route('userzone.inline.page', [$portfolio, 'home']), ['field' => 'name', 'value' => 'Blocked'])->assertUnprocessable()->assertJsonValidationErrors('portfolio');

    expect($portfolio->fresh()->cv_content['name'])->toBe('Sam Example');
})->with(['queued', 'running', 'failed']);

it('keeps edit controls off public snapshots and on successfully generated owner pages only', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $portfolio->update(['published_snapshot' => app(BuildPortfolioSnapshot::class)->draft($portfolio), 'published_at' => now()]);
    $project = $portfolio->projects()->first();
    $this->actingAs($portfolio->user);

    $this->get(route('userzone.preview.home', $portfolio))->assertOk()->assertSee('Edit Name')->assertSee('Edit Title')->assertSee('Edit Introduction');
    $this->get(route('userzone.preview.cv', $portfolio))->assertOk()->assertSee('Edit Role')->assertSee('Edit Phone');
    $this->get(route('portfolio.home', [$portfolio->user->username, $portfolio->slug]))->assertOk()->assertDontSee('data-inline-editor', false)->assertDontSee('data-inline-form', false);
    $this->get(route('portfolio.cv', [$portfolio->user->username, $portfolio->slug]))->assertOk()->assertDontSee('data-inline-editor', false);
    $this->get(route('portfolio.project', [$portfolio->user->username, $portfolio->slug, $project->slug]))->assertOk()->assertDontSee('Add images')->assertDontSee('data-inline-editor', false);
});

it('requires sign in and returns 404 for other owners and mismatched project parents', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $project = $portfolio->projects()->first();
    $url = route('userzone.inline.page', [$portfolio, 'home']);
    $this->patch($url, ['field' => 'name', 'value' => 'Blocked'])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->patchJson($url, ['field' => 'name', 'value' => 'Blocked'])->assertNotFound();
    $this->patchJson(route('userzone.inline.project', [$portfolio, $project]), ['field' => 'title', 'value' => 'Blocked'])->assertNotFound();
    $other = Portfolio::factory()->for($portfolio->user)->create();
    $this->actingAs($portfolio->user)->patchJson(route('userzone.inline.project', [$other, $project]), ['field' => 'title', 'value' => 'Blocked'])->assertNotFound();

    expect($portfolio->fresh()->cv_content['name'])->toBe('Sam Example');
});

it('renders and saves existing education, language, certification and contact fields', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $cv = $portfolio->cv_content;
    $cv['education'] = [['id' => 'degree', 'institution' => 'Example University', 'qualification' => 'Computer science', 'current' => false]];
    $cv['languages'] = [['id' => 'english', 'name' => 'English', 'proficiency' => 'Fluent']];
    $cv['certifications'] = [['id' => 'certificate', 'name' => 'Example certificate', 'issuer' => 'Example issuer', 'date' => '2024', 'url' => 'https://example.com/certificate']];
    $cv['contact']['links'] = [['id' => 'website', 'label' => 'Website', 'url' => 'https://example.com']];
    $portfolio->update(['cv_content' => $cv]);
    $this->actingAs($portfolio->user);
    $url = route('userzone.inline.page', [$portfolio, 'cv']);

    $this->get(route('userzone.preview.cv', $portfolio))->assertOk()->assertSee('Edit Institution')->assertSee('Edit Proficiency')->assertSee('Edit Credential URL')->assertSee('Edit Link URL');
    $this->patchJson($url, ['section' => 'education', 'entry_id' => 'degree', 'field' => 'institution', 'value' => 'New University'])->assertOk();
    $this->patchJson($url, ['section' => 'languages', 'entry_id' => 'english', 'field' => 'proficiency', 'value' => 'Native'])->assertOk();
    $this->patchJson($url, ['section' => 'certifications', 'entry_id' => 'certificate', 'field' => 'url', 'value' => 'https://example.com/new-certificate'])->assertOk();
    $this->patchJson($url, ['section' => 'contact', 'field' => 'phone', 'value' => '+34 123 456 789'])->assertOk();
    $this->patchJson($url, ['section' => 'links', 'entry_id' => 'website', 'field' => 'label', 'value' => 'My website'])->assertOk();

    $portfolio->refresh();
    expect($portfolio->cv_content['education'][0]['institution'])->toBe('New University');
    expect($portfolio->cv_content['languages'][0]['proficiency'])->toBe('Native');
    expect($portfolio->cv_content['certifications'][0]['url'])->toBe('https://example.com/new-certificate');
    expect($portfolio->cv_content['contact']['phone'])->toBe('+34 123 456 789');
    expect($portfolio->cv_content['contact']['links'][0]['label'])->toBe('My website');
});

it('opens generated portfolios from the workspace and keeps manual draft settings accessible', function () {
    $portfolio = Portfolio::factory()->generated()->create();
    $manual = Portfolio::factory()->for($portfolio->user)->create();

    $this->actingAs($portfolio->user)->get(route('dashboard'))->assertOk()
        ->assertSee('href="'.route('userzone.preview.home', $portfolio).'"', false)
        ->assertSee('href="'.route('userzone.portfolios.show', $manual).'"', false)
        ->assertDontSee('href="'.route('userzone.portfolios.show', $portfolio).'"', false);
});
