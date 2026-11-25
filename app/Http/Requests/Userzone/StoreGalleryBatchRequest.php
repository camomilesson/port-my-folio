<?php

namespace App\Http\Requests\Userzone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreGalleryBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('project')->portfolio_id === $this->route('portfolio')->id, 404);
        Gate::authorize('update', $this->route('project'));

        return true;
    }

    public function rules(): array
    {
        $files = $this->file('images', []);
        $count = is_array($files) ? count($files) : 0;

        return [
            'images' => ['required', 'array', 'list', 'min:1', 'max:10'],
            'images.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
            'details' => ['required', 'array', 'list', 'size:'.$count],
            'details.*' => ['required', 'array:caption,alt_text,decorative'],
            'details.*.caption' => ['nullable', 'string', 'max:500'],
            'details.*.decorative' => ['required', 'boolean'],
            'details.*.alt_text' => ['required_unless:details.*.decorative,1,true', 'nullable', 'string', 'max:500'],
        ];
    }
}
