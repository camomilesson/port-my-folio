<?php

namespace App\Http\Requests\Userzone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveGalleryImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('project'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->route('media')
            ? ['decorative' => $this->input('decorative', false)]
            : ['caption' => null, 'alt_text' => '', 'decorative' => true]);
    }

    public function rules(): array
    {
        return [
            'image' => $this->route('media') ? ['prohibited'] : ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
            'caption' => ['nullable', 'string', 'max:500'], 'decorative' => ['required', 'boolean'],
            'alt_text' => ['required_unless:decorative,1,true', 'nullable', 'string', 'max:500'],
        ];
    }
}
