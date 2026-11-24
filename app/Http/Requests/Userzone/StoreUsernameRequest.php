<?php

namespace App\Http\Requests\Userzone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUsernameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->username === null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => Str::lower(trim($this->input('username')))]);
        }
    }

    public function rules(): array
    {
        return ['username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', Rule::notIn(config('portmyfolio.reserved_usernames')), Rule::unique('users', 'username')]];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Start with a letter and use lowercase letters, numbers, and single hyphens.',
            'username.not_in' => 'This username is reserved. Please choose another.',
            'username.unique' => 'This username is already taken.',
        ];
    }
}
