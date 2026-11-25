<?php

use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('stores one immutable private source PDF and lets only its owner download it', function () {
    $portfolio = Portfolio::factory()->create();
    $bytes = file_get_contents(resource_path('fixtures/demo-cv.pdf'));
    $this->actingAs($portfolio->user);

    $this->post(route('userzone.cv-document.store', $portfolio), ['pdf' => UploadedFile::fake()->createWithContent('source.pdf', $bytes)])->assertSessionHasNoErrors();
    $document = $portfolio->cvDocument()->sole();
    expect($document->storage_disk)->toBe('local');
    expect($document->status)->toBe('uploaded');
    Storage::disk('local')->assertExists($document->storage_path);
    $this->get(route('userzone.cv-document.show', $portfolio))->assertOk()->assertDownload('source-cv.pdf')->assertHeader('Content-Type', 'application/pdf');
    $this->post(route('userzone.cv-document.store', $portfolio), ['pdf' => UploadedFile::fake()->createWithContent('replacement.pdf', $bytes)])->assertSessionHasErrors('pdf');
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
    expect($portfolio->fresh()->cv_content['allow_original_download'])->toBeFalse();
    $this->actingAs(User::factory()->create())->get(route('userzone.cv-document.show', $portfolio))->assertNotFound();
    $this->post(route('userzone.cv-document.store', $portfolio), ['pdf' => UploadedFile::fake()->createWithContent('stolen.pdf', $bytes)])->assertNotFound();
});

it('rejects non-PDF, oversized, and disguised uploads', function (string $case) {
    $portfolio = Portfolio::factory()->create();
    $file = match ($case) {
        'oversized' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
        'disguised' => UploadedFile::fake()->createWithContent('fake.pdf', 'not a PDF'),
        default => UploadedFile::fake()->image('image.png'),
    };

    $this->actingAs($portfolio->user)->post(route('userzone.cv-document.store', $portfolio), ['pdf' => $file])->assertSessionHasErrors('pdf');

    $this->assertDatabaseCount('cv_documents', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['oversized', 'disguised', 'image']);

it('cannot introduce a new source after a successful generation', function () {
    $portfolio = Portfolio::factory()->create();
    Generation::factory()->for($portfolio)->succeeded()->create();

    $this->actingAs($portfolio->user)->post(route('userzone.cv-document.store', $portfolio), ['pdf' => UploadedFile::fake()->createWithContent('new.pdf', file_get_contents(resource_path('fixtures/demo-cv.pdf')))])->assertSessionHasErrors('pdf');

    $this->assertDatabaseCount('cv_documents', 0);
});
