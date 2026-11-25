<?php

namespace App\Actions;

use App\Models\Portfolio;
use Illuminate\Support\Facades\Validator;

class GenerationContent
{
    /** @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    public static function validate(array $content, bool $extraction): array
    {
        $rules = PortfolioContent::cvRules();
        $rules['name'] = ['present', 'nullable', 'string', 'max:120'];
        $rules['headline'] = ['present', 'nullable', 'string', 'max:120'];
        $cv = $extraction ? $content : ($content['cv_content'] ?? []);
        if (! is_array($cv) || array_diff(array_keys($cv), ['name', 'headline', 'summary', 'contact', 'experience', 'education', 'skills', 'languages', 'certifications']) !== []) {
            throw new ProviderFailure('invalid_output', 'The provider returned invalid CV data.');
        }
        $validator = Validator::make($cv, $rules);
        $validator->after(fn ($validator) => PortfolioContent::validateDates($validator, $cv));
        if ($validator->fails()) {
            throw new ProviderFailure('invalid_output', 'The provider returned invalid CV data.');
        }
        if (! $extraction) {
            $validator = Validator::make(['content' => $content], [
                'content' => ['required', 'array:home_content,cv_content,projects'],
                'content.home_content' => ['required', 'array:name,headline,introduction'],
                'content.home_content.name' => ['present', 'nullable', 'string', 'max:120'],
                'content.home_content.headline' => ['present', 'nullable', 'string', 'max:120'],
                'content.home_content.introduction' => ['present', 'nullable', 'string', 'max:3000'],
                'content.projects' => ['required', 'array', 'list', 'min:1', 'max:5'],
                'content.projects.*' => ['required', 'array:repository_owner,repository_name,title,summary,overview,technologies,live_url'],
                'content.projects.*.repository_owner' => ['required', 'string', 'regex:/^[a-zA-Z0-9-]+$/', 'max:39'],
                'content.projects.*.repository_name' => ['required', 'string', 'regex:/^[a-zA-Z0-9_.-]+$/', 'max:100'],
                'content.projects.*.title' => ['present', 'nullable', 'string', 'max:120'],
                'content.projects.*.summary' => ['present', 'nullable', 'string', 'max:1000'],
                'content.projects.*.overview' => ['present', 'nullable', 'string', 'max:10000'],
                'content.projects.*.technologies' => ['present', 'array', 'list', 'max:30'],
                'content.projects.*.technologies.*' => ['string', 'max:80'],
                'content.projects.*.live_url' => ['present', 'nullable', 'url:http,https', 'max:2048'],
            ]);
            if ($validator->fails()) {
                throw new ProviderFailure('invalid_output', 'The provider returned invalid portfolio data.');
            }
        }

        return $content;
    }

    /** @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    public static function validateProfile(array $content): array
    {
        if (array_diff(array_keys($content), ['home_content', 'cv_content']) !== []) {
            throw new ProviderFailure('invalid_output', 'The provider returned invalid profile data.');
        }
        self::validate($content + ['projects' => [self::emptyProject()]], false);
        foreach (['name', 'headline'] as $field) {
            if ($content['home_content'][$field] !== $content['cv_content'][$field]) {
                throw new ProviderFailure('invalid_output', 'The provider returned inconsistent identity.');
            }
        }

        return $content;
    }

    /** @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    public static function validateProject(array $content): array
    {
        $cv = PortfolioContent::cv(new Portfolio);
        unset($cv['schema_version']);
        self::validate(['home_content' => ['name' => '', 'headline' => '', 'introduction' => ''], 'cv_content' => $cv, 'projects' => [$content]], false);

        return $content;
    }

    /** @return array<string, mixed> */
    private static function emptyProject(): array
    {
        return ['repository_owner' => 'placeholder', 'repository_name' => 'placeholder', 'title' => '', 'summary' => '', 'overview' => '', 'technologies' => [], 'live_url' => null];
    }

    /** @return array<string, mixed> */
    public static function schema(bool $extraction): array
    {
        $text = ['type' => 'string'];
        $optional = ['type' => ['string', 'null']];
        $entry = fn (array $properties): array => ['type' => 'array', 'items' => self::object($properties)];
        $cv = self::object([
            'name' => $optional, 'headline' => $optional, 'summary' => $optional,
            'contact' => self::object(['email' => $optional, 'phone' => $optional, 'location' => $optional, 'links' => $entry(['id' => $text, 'label' => $text, 'url' => $text])]),
            'experience' => $entry(['id' => $text, 'organization' => $text, 'role' => $text, 'location' => $optional, 'start_date' => $optional, 'end_date' => $optional, 'current' => ['type' => 'boolean'], 'description' => $optional]),
            'education' => $entry(['id' => $text, 'institution' => $text, 'qualification' => $text, 'location' => $optional, 'start_date' => $optional, 'end_date' => $optional, 'current' => ['type' => 'boolean'], 'description' => $optional]),
            'skills' => $entry(['id' => $text, 'name' => $text, 'items' => ['type' => 'array', 'items' => $text]]),
            'languages' => $entry(['id' => $text, 'name' => $text, 'proficiency' => $optional]),
            'certifications' => $entry(['id' => $text, 'name' => $text, 'issuer' => $optional, 'date' => $optional, 'url' => $optional]),
        ]);
        if ($extraction) {
            return $cv;
        }

        return self::object([
            'home_content' => self::object(['name' => $optional, 'headline' => $optional, 'introduction' => $optional]),
            'cv_content' => $cv,
            'projects' => $entry(['repository_owner' => $text, 'repository_name' => $text, 'title' => $optional, 'summary' => $optional, 'overview' => $optional, 'technologies' => ['type' => 'array', 'items' => $text], 'live_url' => $optional]),
        ]);
    }

    /** @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    private static function object(array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }
}
