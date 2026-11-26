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
        return [
            'images' => ['required', 'array', 'list', 'min:1', 'max:10'],
            'images.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ];
    }
}
