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
        $content['allow_original_download'] ??= false;
        $this->replace($content);
    }

    public function rules(): array
    {
        $date = ['nullable', 'string', 'regex:/^(?:19|20|21)\d{2}(?:-(?:0[1-9]|1[0-2]))?$/'];
        $rules = [
            '_complete_cv_form' => ['required', 'accepted'],
            'name' => ['required', 'string', 'max:120'], 'headline' => ['required', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:5000'], 'allow_original_download' => ['required', 'boolean'],
            'contact' => ['required', 'array:email,phone,location,links'],
            'contact.email' => ['nullable', 'email:rfc', 'max:254'],
            'contact.phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9() .\-]+$/'],
            'contact.location' => ['nullable', 'string', 'max:120'],
            'contact.links' => ['present', 'array', 'list', 'max:10'],
            'contact.links.*' => ['array:id,label,url'],
            'contact.links.*.id' => ['required', 'string', 'max:64', 'distinct'],
            'contact.links.*.label' => ['required', 'string', 'max:80'],
            'contact.links.*.url' => ['required', 'url:http,https', 'max:2048'],
        ];
        foreach (['experience' => ['organization', 'role'], 'education' => ['institution', 'qualification']] as $section => $required) {
            $rules[$section] = ['present', 'array', 'list', 'max:30'];
            $rules[$section.'.*'] = ['array:id,'.implode(',', $required).',location,start_date,end_date,current,description'];
            $rules[$section.'.*.id'] = ['required', 'string', 'max:64', 'distinct'];
            foreach ($required as $field) {
                $rules[$section.'.*.'.$field] = ['required', 'string', 'max:160'];
            }
            $rules[$section.'.*.location'] = ['nullable', 'string', 'max:120'];
            $rules[$section.'.*.description'] = ['nullable', 'string', 'max:5000'];
            $rules[$section.'.*.start_date'] = $date;
            $rules[$section.'.*.end_date'] = $date;
            $rules[$section.'.*.current'] = ['required', 'boolean'];
        }
        foreach (['skills', 'languages', 'certifications'] as $section) {
            $rules[$section] = ['present', 'array', 'list', 'max:30'];
            $rules[$section.'.*.id'] = ['required', 'string', 'max:64', 'distinct'];
            $rules[$section.'.*.name'] = ['required', 'string', 'max:160'];
        }

        return $rules + [
            'skills.*' => ['array:id,name,items'], 'skills.*.items' => ['required', 'array', 'list', 'max:40'],
            'skills.*.items.*' => ['required', 'string', 'max:80', 'distinct'],
            'languages.*' => ['array:id,name,proficiency'], 'languages.*.proficiency' => ['nullable', 'string', 'max:120'],
            'certifications.*' => ['array:id,name,issuer,date,url'],
            'certifications.*.issuer' => ['nullable', 'string', 'max:160'],
            'certifications.*.date' => $date,
            'certifications.*.url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return ['_complete_cv_form.required' => 'The CV form was incomplete. No changes were saved. Try fewer entries or contact support.'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach (['experience', 'education'] as $section) {
                foreach ($this->input($section, []) as $index => $entry) {
                    $start = $entry['start_date'] ?? null;
                    $end = $entry['end_date'] ?? null;
                    if (($entry['current'] ?? false) && $end) {
                        $validator->errors()->add("$section.$index.end_date", 'Clear the end date when this entry is current.');
                    } elseif ($start && $end && ($end.(strlen($end) === 4 ? '-12' : '')) < ($start.(strlen($start) === 4 ? '-01' : ''))) {
                        $validator->errors()->add("$section.$index.end_date", 'The end date must not be earlier than the start date.');
                    }
                }
            }
        }];
    }
}
