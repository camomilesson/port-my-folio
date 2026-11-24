<?php

namespace App\Actions;

use App\Models\Portfolio;

class PortfolioContent
{
    /** @return array<string, mixed> */
    public static function cv(Portfolio $portfolio): array
    {
        $cv = array_replace_recursive([
            'schema_version' => 1, 'name' => '', 'headline' => '', 'summary' => '',
            'contact' => ['email' => '', 'phone' => '', 'location' => '', 'links' => []],
            'experience' => [], 'education' => [], 'skills' => [], 'languages' => [],
            'certifications' => [], 'allow_original_download' => false,
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

        return $cv;
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
}
