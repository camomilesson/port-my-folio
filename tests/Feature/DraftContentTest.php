<?php

use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('saves home content and shared identity without changing evidence or the published snapshot', function () {
    $portfolio = Portfolio::factory()->published()->create();
    $snapshot = $portfolio->published_snapshot;
    $summary = $portfolio->cv_content['summary'];

    $this->actingAs($portfolio->user)->put(route('userzone.content.home.update', $portfolio), ['title' => 'Updated title', 'name' => 'Display Name', 'headline' => 'Engineer', 'introduction' => 'Brief home intro', 'focus' => 'A new audience', 'user_id' => 999, 'published_snapshot' => []])->assertRedirect();

    $portfolio->refresh();
    expect($portfolio->home_content['name'])->toBe('Display Name');
    expect($portfolio->cv_content['name'])->toBe('Display Name');
    expect($portfolio->cv_content['summary'])->toBe($summary);
    expect($portfolio->published_snapshot)->toBe($snapshot);
});

it('saves CV entries in submitted order with shared identity and separate summaries', function () {
    $portfolio = Portfolio::factory()->create();
    $intro = $portfolio->home_content['introduction'];
    $cv = $portfolio->cv_content;
    unset($cv['schema_version']);
    $cv['_complete_cv_form'] = '1';
    $cv['name'] = 'New shared name';
    $cv['experience'] = [
        ['id' => 'second', 'organization' => 'Second', 'role' => 'Developer', 'start_date' => '2022', 'end_date' => '2023-01', 'current' => false],
        ['id' => 'first', 'organization' => 'First', 'role' => 'Engineer', 'start_date' => '2024-02', 'current' => true],
    ];
    $cv['skills'] = [['name' => 'Backend', 'items_text' => "PHP\nLaravel"]];
    $cv['certifications'] = [['name' => 'Certificate', 'issuer' => 'University', 'date' => '2020', 'url' => 'https://example.com/certificate']];
    $cv['allow_original_download'] = true;

    $this->actingAs($portfolio->user)->put(route('userzone.content.cv.update', $portfolio), $cv)->assertRedirect()->assertSessionHasNoErrors();

    $portfolio->refresh();
    expect(array_column($portfolio->cv_content['experience'], 'id'))->toBe(['second', 'first']);
    expect($portfolio->cv_content['skills'][0]['items'])->toBe(['PHP', 'Laravel']);
    expect($portfolio->cv_content['skills'][0]['id'])->toBeString();
    expect($portfolio->home_content['name'])->toBe('New shared name');
    expect($portfolio->home_content['introduction'])->toBe($intro);
    expect($portfolio->cv_content)->not->toHaveKey('allow_original_download');

    $cv['experience'] = [];
    $this->put(route('userzone.content.cv.update', $portfolio), $cv)->assertSessionHasNoErrors();
    expect($portfolio->fresh()->cv_content['experience'])->toBe([]);
});

it('rejects unsafe links, unknown nested keys, invalid dates, and inconsistent current entries', function (string $field, mixed $value) {
    $portfolio = Portfolio::factory()->create();
    $cv = $portfolio->cv_content;
    unset($cv['schema_version']);
    $cv['_complete_cv_form'] = '1';
    data_set($cv, $field, $value);

    $this->actingAs($portfolio->user)->put(route('userzone.content.cv.update', $portfolio), $cv)->assertSessionHasErrors();

    expect($portfolio->fresh()->cv_content)->toBe($portfolio->cv_content);
})->with([
    'unsafe link' => ['contact.links', [['label' => 'Bad', 'url' => 'javascript:alert(1)']]],
    'invalid month' => ['experience.0.start_date', '2024-13'],
    'reversed range' => ['experience.0.start_date', '2200'],
    'current with end' => ['experience.0.end_date', '2025'],
    'unknown contact field' => ['contact.admin', true],
    'too much text' => ['summary', str_repeat('x', 5001)],
    'wrong shape' => ['skills', 'not-an-array'],
]);

it('blocks edits while initial generation is active', function () {
    $portfolio = Portfolio::factory()->create();
    Generation::factory()->for($portfolio)->create(['status' => 'running']);

    $this->actingAs($portfolio->user)->put(route('userzone.content.home.update', $portfolio), ['title' => 'Blocked', 'name' => 'Name', 'headline' => 'Headline'])->assertSessionHasErrors('portfolio');

    expect($portfolio->fresh()->title)->not->toBe('Blocked');
});

it('protects editor forms and content updates from another owner', function () {
    $portfolio = Portfolio::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('userzone.content.home', $portfolio))->assertNotFound();
    $this->get(route('userzone.content.cv', $portfolio))->assertNotFound();
    $this->put(route('userzone.content.home.update', $portfolio), [])->assertNotFound();
    $this->put(route('userzone.content.cv.update', $portfolio), [])->assertNotFound();
});

it('renders home and CV forms including seeded and empty repeated sections', function () {
    $portfolio = Portfolio::factory()->create();
    $this->actingAs($portfolio->user)->get(route('userzone.content.home', $portfolio))->assertOk()->assertSee('Save home draft');
    $this->get(route('userzone.content.cv', $portfolio))->assertOk()->assertSee('Save CV draft')->assertSee('Experience')->assertSee('Original PDF');
});

it('refuses a truncated CV form rather than wiping omitted sections', function () {
    $portfolio = Portfolio::factory()->create();
    $cv = $portfolio->cv_content;
    unset($cv['schema_version']);

    $this->actingAs($portfolio->user)->put(route('userzone.content.cv.update', $portfolio), $cv)->assertSessionHasErrors('_complete_cv_form');

    expect($portfolio->fresh()->cv_content)->toBe($portfolio->cv_content);
});
