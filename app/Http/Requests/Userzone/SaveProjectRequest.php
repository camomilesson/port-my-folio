<?php

namespace App\Http\Requests\Userzone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('portfolio'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('technologies_text'))) {
            $this->merge(['technologies' => array_values(array_filter(array_map('trim', explode("\n", $this->input('technologies_text')))))]);
        }
    }

    public function rules(): array
    {
        return [
            'repository_url' => $this->route('project') ? ['prohibited'] : ['required', 'string', 'max:250', 'regex:~^https://github\.com/[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,38})/[a-zA-Z0-9_.-]{1,100}/?$~'],
            'title' => ['required', 'string', 'max:120'], 'author_role' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'], 'overview' => ['nullable', 'string', 'max:10000'],
            'technical_details' => ['required', 'string', 'max:10000'],
            'technologies' => ['sometimes', 'array', 'list', 'max:30'],
            'technologies.*' => ['required', 'string', 'max:80', 'distinct'],
            'live_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return ['repository_url.regex' => 'Enter a repository URL such as https://github.com/owner/repository, without extra paths or query parameters.'];
    }
}
