<?php

namespace App\Actions;

use App\Models\Portfolio;
use Illuminate\Validation\Validator;

class PortfolioContent
{
    /** @return array<string, mixed> */
    public static function cv(Portfolio $portfolio): array
    {
        $cv = array_replace_recursive([
            'schema_version' => 1, 'name' => '', 'headline' => '', 'summary' => '',
            'contact' => ['email' => '', 'phone' => '', 'location' => '', 'links' => []],
            'experience' => [], 'education' => [], 'skills' => [], 'languages' => [],
            'certifications' => [],
        ], $portfolio->cv_content ?? []);
        if (isset($cv['skills'][0]) && is_string($cv['skills'][0])) {
            $cv['skills'] = [['id' => 'skills', 'name' => 'Skills', 'items' => $cv['skills']]];
        }
        foreach (['experience', 'education'] as $section) {
            foreach ($cv[$section] as &$entry) {
                $entry['current'] ??= false;
            }
            unset($entry);
        }

        unset($cv['allow_original_download']);

        return $cv;
    }

    public static function cvRules(): array
    {
        $date = ['nullable', 'string', 'regex:/^(?:19|20|21)\d{2}(?:-(?:0[1-9]|1[0-2]))?$/'];
        $rules = [
            'name' => ['required', 'string', 'max:120'], 'headline' => ['required', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:5000'],
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

    /** @return array<string, array<string, string>> */
    public static function sections(): array
    {
        return [
            'experience' => ['organization' => 'Organization', 'role' => 'Role', 'location' => 'Location', 'start_date' => 'Start date', 'end_date' => 'End date', 'description' => 'Description'],
            'education' => ['institution' => 'Institution', 'qualification' => 'Qualification / subject', 'location' => 'Location', 'start_date' => 'Start date', 'end_date' => 'End date', 'description' => 'Description'],
            'skills' => ['name' => 'Group name', 'items_text' => 'Skills (one per line)'],
            'languages' => ['name' => 'Language', 'proficiency' => 'Proficiency'],
            'certifications' => ['name' => 'Certification', 'issuer' => 'Issuer', 'date' => 'Date', 'url' => 'Credential URL'],
        ];
    }

    /** @param array<string, mixed> $content */
    public static function validateDates(Validator $validator, array $content): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        foreach (['experience', 'education'] as $section) {
            foreach (($content[$section] ?? []) as $index => $entry) {
                $start = $entry['start_date'] ?? null;
                $end = $entry['end_date'] ?? null;
                if (($entry['current'] ?? false) && $end) {
                    $validator->errors()->add("$section.$index.end_date", 'Clear the end date when this entry is current.');
                } elseif ($start && $end && ($end.(strlen($end) === 4 ? '-12' : '')) < ($start.(strlen($start) === 4 ? '-01' : ''))) {
                    $validator->errors()->add("$section.$index.end_date", 'The end date must not be earlier than the start date.');
                }
            }
        }
    }
}
