<?php

namespace App\Http\Requests\Userzone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateHomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('portfolio'));

        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'focus' => ['nullable', 'string', 'max:5000'],
            'name' => ['required', 'string', 'max:120'],
            'headline' => ['required', 'string', 'max:120'],
            'introduction' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
