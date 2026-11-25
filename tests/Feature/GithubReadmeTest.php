<?php

use App\Actions\FetchGithubReadme;
use App\Actions\ProviderFailure;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['cache.default' => 'array']);
    Http::preventStrayRequests();
});

it('fetches full public README evidence and caches it across normalized URLs', function () {
    $this->freezeTime();
    $readme = str_repeat('This project helps students track their assignments. ', 20);
    Http::fake([
        'https://api.github.com/repos/example/course' => Http::response(['private' => false]),
        'https://api.github.com/repos/example/course/readme' => Http::response($readme),
    ]);
    $fetch = app(FetchGithubReadme::class);
    $evidence = $fetch->handle('https://github.com/Example/Course.git');
    expect($evidence['content'])->toBe($readme);
    expect($fetch->handle('https://github.com/example/course/'))->toBe($evidence);
    Http::assertSentCount(2);
});

it('expires cached evidence after one hour', function () {
    $this->freezeTime();
    $readme = str_repeat('A useful project description. ', 20);
    Http::fake([
        'https://api.github.com/repos/example/course' => Http::sequence()->push(['private' => false])->push(['private' => false]),
        'https://api.github.com/repos/example/course/readme' => Http::sequence()->push($readme)->push($readme),
    ]);
    app(FetchGithubReadme::class)->handle('https://github.com/example/course');
    $this->travel(61)->minutes();
    app(FetchGithubReadme::class)->handle('https://github.com/example/course');
    Http::assertSentCount(4);
});

it('enforces strict meaningful character and byte boundaries', function (string $content, bool $valid) {
    if ($valid) {
        app(FetchGithubReadme::class)->validateContent($content);
        expect(true)->toBeTrue();
    } else {
        expect(fn () => app(FetchGithubReadme::class)->validateContent($content))->toThrow(ProviderFailure::class);
    }
})->with([
    '200 characters rejected' => [str_repeat('a', 200), false],
    '201 characters accepted' => [str_repeat('a', 201), true],
    '99999 bytes accepted' => [str_repeat('a', 99999), true],
    '100000 bytes rejected' => [str_repeat('a', 100000), false],
    'badges and links do not count' => [str_repeat('[![Build](https://example.com/badge)](https://example.com)\n', 20), false],
    'comments do not count' => ['<!--'.str_repeat('hidden', 200).'-->', false],
]);

it('rejects unsafe repository URLs without making requests', function (string $url) {
    Http::fake();
    expect(fn () => app(FetchGithubReadme::class)->handle($url))->toThrow(ValidationException::class);
    Http::assertNothingSent();
})->with(['http://github.com/example/course', 'https://evil.com/example/course', 'https://github.com/example/course/tree/main', 'https://github.com/example/..', 'https://github.com/example/course?token=secret']);

it('does not inspect private repositories or follow redirects', function (int $status, array $body) {
    Http::fake(['https://api.github.com/repos/example/course' => Http::response($body, $status, ['Location' => 'https://elsewhere.example'])]);
    expect(fn () => app(FetchGithubReadme::class)->handle('https://github.com/example/course'))->toThrow(ProviderFailure::class);
    Http::assertSentCount(1);
})->with([[200, ['private' => true]], [404, []], [301, []], [503, []]]);

it('retains completed evidence and defers requests until the GitHub reset', function () {
    $this->freezeTime();
    $reset = time() + 120;
    Http::fake(['https://api.github.com/repos/example/course' => Http::sequence()->push([], 429, ['Retry-After' => '120'])->push(['private' => false]),
        'https://api.github.com/repos/example/course/readme' => Http::response(str_repeat('Project description. ', 30))]);
    try {
        app(FetchGithubReadme::class)->handle('https://github.com/example/course');
        $this->fail('Expected rate limiting.');
    } catch (ProviderFailure $exception) {
        expect($exception->errorCode)->toBe('github_rate_limited');
        expect($exception->retryAt)->toBe($reset);
    }
    expect(fn () => app(FetchGithubReadme::class)->handle('https://github.com/example/course'))->toThrow(ProviderFailure::class);
    Http::assertSentCount(1);
    $this->travel(121)->seconds();
    expect(app(FetchGithubReadme::class)->handle('https://github.com/example/course')['owner'])->toBe('example');
});

it('bounds the downloaded body even when GitHub omits its size header', function () {
    Http::fake([
        'https://api.github.com/repos/example/course' => Http::response(['private' => false]),
        'https://api.github.com/repos/example/course/readme' => Http::response(str_repeat('a', 100000)),
    ]);
    expect(fn () => app(FetchGithubReadme::class)->handle('https://github.com/example/course'))->toThrow(ProviderFailure::class, 'smaller than 100 KB');
    Http::assertSentCount(2);
});
