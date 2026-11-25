<?php

namespace App\Actions;

use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EditInlinePortfolioField
{
    public function __construct(private EditPortfolioDraft $editor) {}

    public static function requireReady(Portfolio $portfolio): void
    {
        if ($portfolio->generations()->latest('id')->value('status') !== 'succeeded') {
            throw ValidationException::withMessages(['portfolio' => 'Finish generation before editing on the portfolio.']);
        }
    }

    public function page(Portfolio $portfolio, string $scope, string $field, mixed $value, ?string $section, ?string $entryId): void
    {
        $this->editor->handle($portfolio, function (Portfolio $locked) use ($scope, $field, $value, $section, $entryId): void {
            self::requireReady($locked);
            if ($scope === 'home' && $section === null && in_array($field, ['name', 'headline', 'introduction'], true)) {
                Validator::make(['value' => $value], ['value' => $field === 'introduction' ? ['nullable', 'string', 'max:3000'] : ['required', 'string', 'max:120']])->validate();
                $home = $locked->home_content ?? [];
                $home[$field] = $value;
                $updates = ['home_content' => $home];
                if (in_array($field, ['name', 'headline'], true)) {
                    $cv = PortfolioContent::cv($locked);
                    $cv[$field] = $value;
                    $updates['cv_content'] = $cv;
                }
                $locked->update($updates);

                return;
            }
            if ($scope !== 'cv') {
                throw ValidationException::withMessages(['field' => 'This field cannot be edited here.']);
            }
            $cv = PortfolioContent::cv($locked);
            unset($cv['schema_version']);
            $allowed = [
                '' => ['name', 'headline', 'summary'], 'contact' => ['email', 'phone', 'location'],
                'links' => ['label', 'url'], 'experience' => ['organization', 'role', 'location', 'start_date', 'end_date', 'current', 'description'],
                'education' => ['institution', 'qualification', 'location', 'start_date', 'end_date', 'current', 'description'],
                'skills' => ['name', 'items'], 'languages' => ['name', 'proficiency'], 'certifications' => ['name', 'issuer', 'date', 'url'],
            ];
            $section ??= '';
            if (! in_array($field, $allowed[$section] ?? [], true)) {
                throw ValidationException::withMessages(['field' => 'This field cannot be edited here.']);
            }
            if ($section === '') {
                $cv[$field] = $value;
            } elseif ($section === 'contact') {
                $cv['contact'][$field] = $value;
            } else {
                $entries = $section === 'links' ? $cv['contact']['links'] : $cv[$section];
                $index = collect($entries)->search(fn (array $entry): bool => $entry['id'] === $entryId);
                if ($index === false) {
                    throw ValidationException::withMessages(['entry_id' => 'This CV entry no longer exists. Refresh the page.']);
                }
                if ($field === 'items') {
                    Validator::make(['value' => $value], ['value' => ['required', 'string', 'max:5000']])->validate();
                    $value = $this->commaList($value);
                }
                $entries[$index][$field] = $value;
                if ($field === 'current') {
                    Validator::make(['value' => $value], ['value' => ['required', 'boolean']])->validate();
                    $entries[$index]['current'] = (bool) $value;
                    if ($entries[$index]['current']) {
                        $entries[$index]['end_date'] = null;
                    }
                }
                if ($section === 'links') {
                    $cv['contact']['links'] = $entries;
                } else {
                    $cv[$section] = $entries;
                }
            }
            $rules = PortfolioContent::cvRules();
            foreach (['name', 'headline'] as $identity) {
                if ($section !== '' || $field !== $identity) {
                    $rules[$identity] = ['nullable', 'string', 'max:120'];
                }
            }
            $validator = Validator::make($cv, $rules);
            $validator->after(fn ($validator) => PortfolioContent::validateDates($validator, $cv));
            $validator->validate();
            $home = array_replace($locked->home_content ?? [], ['schema_version' => 1, 'name' => $cv['name'], 'headline' => $cv['headline']]);
            $locked->update(['cv_content' => ['schema_version' => 1] + $cv, 'home_content' => $home]);
        });
    }

    public function project(Portfolio $portfolio, Project $project, string $field, mixed $value): void
    {
        abort_unless($project->portfolio_id === $portfolio->id, 404);
        $rules = [
            'title' => ['required', 'string', 'max:120'], 'summary' => ['nullable', 'string', 'max:1000'],
            'overview' => ['nullable', 'string', 'max:10000'], 'technical_details' => ['required', 'string', 'max:10000'],
            'technologies' => ['nullable', 'string', 'max:3000'],
        ];
        if (! isset($rules[$field])) {
            throw ValidationException::withMessages(['field' => 'This project field cannot be edited here.']);
        }
        Validator::make(['value' => $value], ['value' => $rules[$field]])->validate();
        if ($field === 'technologies') {
            $value = $this->commaList($value ?? '');
            Validator::make(['value' => $value], ['value' => ['array', 'max:30'], 'value.*' => ['string', 'max:80']])->validate();
        }
        $this->editor->handle($portfolio, function (Portfolio $locked) use ($project, $field, $value): void {
            self::requireReady($locked);
            $locked->projects()->findOrFail($project->id)->update([$field => $value]);
        });
    }

    /** @return list<string> */
    private function commaList(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), fn (string $item): bool => $item !== '')));
    }
}
