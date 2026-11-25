<?php

use App\Actions\FakePortfolioProvider;
use App\Actions\GeminiPortfolioProvider;
use App\Actions\ProviderFailure;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.gemini.key' => 'test-secret', 'services.gemini.model' => 'gemini-3.8-flash']);
    Http::preventStrayRequests();
});

it('sends the PDF natively and validates extraction while retaining usage metadata', function () {
    $cv = FakePortfolioProvider::cv();
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::response([
        'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($cv)]]]]],
        'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 50],
    ])]);
    $bytes = file_get_contents(resource_path('fixtures/demo-cv.pdf'));
    $result = app(GeminiPortfolioProvider::class)->extractCv($bytes);
    expect($result->content)->toBe($cv);
    expect($result->usage)->toBe(['promptTokenCount' => 100, 'candidatesTokenCount' => 50]);
    Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'test-secret') && $request['contents'][0]['parts'][0]['inlineData']['data'] === base64_encode($bytes) && $request['generationConfig']['responseMimeType'] === 'application/json');
});

it('rejects malformed or unsafe provider data rather than accepting schema promises', function (array $cv) {
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::response([
        'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($cv)]]]]],
    ])]);
    expect(fn () => app(GeminiPortfolioProvider::class)->extractCv(file_get_contents(resource_path('fixtures/demo-cv.pdf'))))->toThrow(ProviderFailure::class, 'invalid CV data');
    Http::assertSentCount(1);
})->with([
    'unexpected keys' => [FakePortfolioProvider::cv() + ['published_snapshot' => []]],
    'unsafe links' => [array_replace(FakePortfolioProvider::cv(), ['contact' => ['email' => '', 'phone' => '', 'location' => '', 'links' => [['id' => 'link', 'label' => 'Website', 'url' => 'javascript:alert(1)']]]])],
    'invalid dates' => [array_replace(FakePortfolioProvider::cv(), ['experience' => [['id' => 'job', 'organization' => 'Company', 'role' => 'Developer', 'current' => false, 'start_date' => '2025-09', 'end_date' => '2024']]])],
]);

it('returns safe retryable errors without leaking credentials or provider response bodies', function () {
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::response(['error' => ['message' => 'secret provider internals']], 429, ['Retry-After' => '120'])]);
    try {
        app(GeminiPortfolioProvider::class)->extractCv(file_get_contents(resource_path('fixtures/demo-cv.pdf')));
        $this->fail('Expected rate limiting.');
    } catch (ProviderFailure $exception) {
        expect($exception->errorCode)->toBe('provider_retryable');
        expect($exception->retryAt)->toBeGreaterThan(time());
        expect($exception->getMessage())->not->toContain('test-secret', 'secret provider internals');
    }
    Http::assertSentCount(1);
});

it('does not call an unconfigured provider', function () {
    config(['services.gemini.key' => '']);
    Http::fake();
    expect(fn () => app(GeminiPortfolioProvider::class)->extractCv(file_get_contents(resource_path('fixtures/demo-cv.pdf'))))->toThrow(ProviderFailure::class, 'not configured');
    Http::assertNothingSent();
});

it('provides a deterministic offline implementation of extraction and generation', function () {
    Http::fake();
    $provider = new FakePortfolioProvider;
    expect($provider->extractCv('fixture')->content['name'])->toBe('Sam Example');
    expect($provider->generateContent(['projects' => [['repository_owner' => 'example', 'repository_name' => 'course']]])->content['projects'][0]['title'])->toBe('course');
    Http::assertNothingSent();
});

it('rejects incomplete and non-JSON responses', function (array $response) {
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::response($response)]);
    expect(fn () => app(GeminiPortfolioProvider::class)->extractCv(file_get_contents(resource_path('fixtures/demo-cv.pdf'))))->toThrow(ProviderFailure::class);
    Http::assertSentCount(1);
})->with([
    'truncated' => [['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{}']]]]]]],
    'invalid JSON' => [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'not json']]]]]]],
    'blocked' => [['promptFeedback' => ['blockReason' => 'SAFETY']]],
]);

it('rejects invented repository identities in generated content', function () {
    $content = (new FakePortfolioProvider)->generateContent(['projects' => [['repository_owner' => 'invented', 'repository_name' => 'project']]])->content;
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::response([
        'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($content)]]]]],
    ])]);
    expect(fn () => app(GeminiPortfolioProvider::class)->generateContent(['projects' => [['repository_owner' => 'example', 'repository_name' => 'course']]]))->toThrow(ProviderFailure::class, 'inconsistent identity or project data');
    Http::assertSentCount(1);
});

it('requests separate project and profile contracts without regenerating completed projects', function () {
    $fake = new FakePortfolioProvider;
    $evidence = ['project' => ['repository_owner' => 'example', 'repository_name' => 'course']];
    $project = $fake->generateProject($evidence)->content;
    $profile = $fake->generateProfile([])->content;
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::sequence()
        ->push(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($project)]]]]]])
        ->push(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($profile)]]]]]])]);
    expect(app(GeminiPortfolioProvider::class)->generateProject($evidence)->content)->toBe($project);
    expect(app(GeminiPortfolioProvider::class)->generateProfile(['cv' => FakePortfolioProvider::cv(), 'projects' => [$project]])->content)->toBe($profile);
    Http::assertSent(fn ($request) => array_keys($request['generationConfig']['responseJsonSchema']['properties']) === ['home_content', 'cv_content']);
    Http::assertSent(fn ($request) => ! str_contains(json_encode($request->data()), 'author_role'));
    Http::assertSentCount(2);
});

it('respects an HTTP-date retry instruction from Gemini', function () {
    $retryAt = time() + 3600;
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::response([], 429, ['Retry-After' => gmdate('D, d M Y H:i:s', $retryAt).' GMT'])]);
    try {
        app(GeminiPortfolioProvider::class)->extractCv(file_get_contents(resource_path('fixtures/demo-cv.pdf')));
        $this->fail('Expected a rate limit.');
    } catch (ProviderFailure $failure) {
        expect($failure->retryAt)->toBe($retryAt);
    }
    Http::assertSentCount(1);
});
