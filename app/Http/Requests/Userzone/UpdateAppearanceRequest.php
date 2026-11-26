<?php

namespace App\Http\Requests\Userzone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('portfolio'));

        return true;
    }

    public function rules(): array
    {
        return [
            'field' => ['required', Rule::in(['preset', 'accent_color'])],
            'value' => ['required', 'string', $this->input('field') === 'preset'
                ? Rule::in(array_keys(config('portmyfolio.presets')))
                : 'regex:/^#[a-fA-F0-9]{6}$/'],
        ];
    }

    public function attributes(): array
    {
        return ['value' => $this->input('field') === 'preset' ? 'preset' : 'accent color'];
    }
}
