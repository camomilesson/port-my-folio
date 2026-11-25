<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FetchGithubReadme
{
    /** @return array{owner: string, name: string} */
    public function identity(string $url): array
    {
        if (! preg_match('~^https://github\.com/([a-zA-Z0-9](?:[a-zA-Z0-9-]{0,38}))/([a-zA-Z0-9_.-]{1,100})/?$~D', $url, $matches)) {
            throw ValidationException::withMessages(['repository_url' => 'Use https://github.com/owner/repository without extra paths or query parameters.']);
        }
        $name = strtolower(preg_replace('/\.git$/i', '', $matches[2]));
        if (in_array($name, ['', '.', '..'], true)) {
            throw ValidationException::withMessages(['repository_url' => 'Enter a valid GitHub repository name.']);
        }

        return ['owner' => strtolower($matches[1]), 'name' => $name];
    }

    /** @return array{owner: string, name: string, content: string, fetched_at: string} */
    public function handle(string $url): array
    {
        $identity = $this->identity($url);
        $repository = $identity['owner'].'/'.$identity['name'];
        $key = 'github-readme:v1:'.$repository;
        if ($cached = Cache::get($key)) {
            return $cached;
        }
        if ($retryAt = Cache::get('github-preflight:retry-at')) {
            throw new ProviderFailure('github_rate_limited', 'GitHub is temporarily rate-limiting imports. Try again after '.gmdate('H:i', $retryAt).' UTC.', $retryAt);
        }
        $metadata = $this->request('repos/'.$repository);
        if ($metadata->json('private') !== false) {
            throw new ProviderFailure('github_not_public', 'Choose a public GitHub repository.');
        }
        $readme = $this->request('repos/'.$repository.'/readme', true)->body();
        $this->validateContent($readme);
        $evidence = $identity + ['content' => $readme, 'fetched_at' => now()->toIso8601String()];
        Cache::put($key, $evidence, now()->addHour());

        return $evidence;
    }

    public function validateContent(string $content): void
    {
        if (strlen($content) >= 100000) {
            throw new ProviderFailure('readme_too_large', 'Keep the full README smaller than 100 KB (100,000 bytes).');
        }
        $meaningful = preg_replace('/<!--.*?-->/s', '', $content);
        $meaningful = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $meaningful);
        $meaningful = preg_replace('/^\s*(?:\[[^\]]+\]\([^)]*\)\s*)+$/m', '', $meaningful);
        $meaningful = preg_replace('/^\s*\[[^\]]+\]:.*$/m', '', $meaningful);
        $meaningful = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $meaningful);
        $meaningful = preg_replace('~https?://\S+~', '', strip_tags($meaningful));
        $meaningful = trim(preg_replace('/\s+/u', ' ', preg_replace('/[#*`_>~|\[\]]/u', '', $meaningful)));
        if (mb_strlen($meaningful) <= 200) {
            throw new ProviderFailure('readme_insufficient', 'Add more than 200 characters of meaningful README text explaining what the project does, or choose another repository. Badges and link-only content do not count.');
        }
    }

    private function request(string $path, bool $raw = false): Response
    {
        try {
            $response = Http::accept($raw ? 'application/vnd.github.raw+json' : 'application/vnd.github+json')
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'Portmyfolio'])
                ->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false, 'stream' => true])
                ->get('https://api.github.com/'.$path);
        } catch (ConnectionException) {
            throw new ProviderFailure('github_unavailable', 'GitHub could not be reached. Your inputs can be submitted again shortly.');
        }
        if ($response->status() === 429 || ($response->status() === 403 && ($response->header('X-RateLimit-Remaining') === '0' || $response->header('Retry-After') !== null))) {
            $retryAfter = $response->header('Retry-After');
            $retryAt = max(time() + 60, (int) $response->header('X-RateLimit-Reset'), is_numeric($retryAfter) ? time() + (int) $retryAfter : (strtotime($retryAfter ?? '') ?: 0));
            Cache::put('github-preflight:retry-at', $retryAt, $retryAt - time());
            throw new ProviderFailure('github_rate_limited', 'GitHub is rate-limiting imports. Try again after '.gmdate('H:i', $retryAt).' UTC.', $retryAt);
        }
        if ($response->status() === 404) {
            throw new ProviderFailure('github_missing', 'Repository or README not found. Check the address, make the repository public, and add a README.');
        }
        if (! $response->successful()) {
            throw new ProviderFailure('github_unavailable', 'GitHub could not provide this repository. Check its address and try again later.');
        }

        $stream = $response->toPsrResponse()->getBody();
        $body = '';
        try {
            while (! $stream->eof()) {
                $body .= $stream->read(8192);
                if (strlen($body) >= 100000) {
                    throw new ProviderFailure('readme_too_large', 'Keep the full README smaller than 100 KB (100,000 bytes).');
                }
            }
        } catch (\RuntimeException $exception) {
            if ($exception instanceof ProviderFailure) {
                throw $exception;
            }
            throw new ProviderFailure('github_unavailable', 'GitHub could not provide the complete README. Try again later.');
        } finally {
            $stream->close();
        }
        $response = new Response(new \GuzzleHttp\Psr7\Response($response->status(), $response->headers(), $body));

        return $response;
    }
}
