<?php

namespace App\Http\Requests\Userzone;

use App\Models\Portfolio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePortfolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Portfolio::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::lower(trim($this->input('slug')))]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('portfolios', 'slug')->where('user_id', $this->user()->id)],
            'focus' => ['nullable', 'string', 'max:5000'],
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'extensions:pdf', 'max:10240'],
            'repositories' => ['required', 'array', 'list', 'min:1', 'max:5'],
            'repositories.*' => ['required', 'array:repository_url,author_role'],
            'repositories.*.repository_url' => ['required', 'string', 'max:250', 'regex:~^https://github\.com/[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,38})/[a-zA-Z0-9_.-]{1,100}/?$~'],
            'repositories.*.author_role' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers, and single hyphens for the address.',
            'slug.unique' => 'You already have a portfolio with this address.',
        ];
    }
}
