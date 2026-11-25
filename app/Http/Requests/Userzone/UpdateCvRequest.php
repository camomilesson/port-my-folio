<?php

namespace App\Http\Requests\Userzone;

use App\Actions\PortfolioContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UpdateCvRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('portfolio'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $content = $this->all();
        foreach (array_keys(PortfolioContent::sections()) as $section) {
            $content[$section] ??= [];
            if (! is_array($content[$section])) {
                continue;
            }
            foreach ($content[$section] as &$entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $entry['id'] ??= (string) Str::uuid();
                if (in_array($section, ['experience', 'education'], true)) {
                    $entry['current'] ??= false;
                }
                if ($section === 'skills' && is_string($entry['items_text'] ?? null)) {
                    $entry['items'] = array_values(array_filter(array_map('trim', explode("\n", $entry['items_text']))));
                    unset($entry['items_text']);
                }
            }
            unset($entry);
            $content[$section] = array_values($content[$section]);
        }
        $content['contact'] ??= [];
        if (is_array($content['contact'])) {
            $content['contact']['links'] ??= [];
            if (is_array($content['contact']['links'])) {
                $content['contact']['links'] = array_values($content['contact']['links']);
                foreach ($content['contact']['links'] as &$link) {
                    if (is_array($link)) {
                        $link['id'] ??= (string) Str::uuid();
                    }
                }
                unset($link);
            }
        }
        unset($content['allow_original_download']);
        $this->replace($content);
    }

    public function rules(): array
    {
        return ['_complete_cv_form' => ['required', 'accepted']] + PortfolioContent::cvRules();
    }

    public function messages(): array
    {
        return ['_complete_cv_form.required' => 'The CV form was incomplete. No changes were saved. Try fewer entries or contact support.'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            PortfolioContent::validateDates($validator, $this->all());
        }];
    }
}
