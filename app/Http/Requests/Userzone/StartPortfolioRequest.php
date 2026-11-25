<?php

namespace App\Http\Requests\Userzone;

use Illuminate\Support\Str;

class StartPortfolioRequest extends StorePortfolioRequest
{
    protected function prepareForValidation(): void
    {
        $repositories = $this->input('repositories');
        if (is_array($repositories)) {
            foreach ($repositories as &$repository) {
                if (is_array($repository) && is_string($repository['repository_url'] ?? null)) {
                    $url = rtrim(strtolower(trim($repository['repository_url'])), '/');
                    $url = preg_replace('/\.git$/', '', $url);
                    $repository['author_role'] = (config('portmyfolio.demo_repository_roles')[$url] ?? 'Contribution not specified');
                }
            }
            unset($repository);
        }
        $this->merge(['title' => Str::limit($this->user()->name, 95, '').' portfolio', 'slug' => 'portfolio-'.Str::lower(Str::random(12)), 'repositories' => $repositories]);
        parent::prepareForValidation();
    }

    public function rules(): array
    {
        return parent::rules() + ['confirm' => ['accepted']];
    }
}
