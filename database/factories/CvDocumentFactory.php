<?php

namespace Database\Factories;

use App\Models\CvDocument;
use App\Models\Portfolio;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CvDocument> */
class CvDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'storage_path' => 'demo/cvs/'.fake()->uuid().'.pdf',
            'original_filename' => 'demo-cv.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => filesize(resource_path('fixtures/demo-cv.pdf')),
            'status' => 'ready',
            'extracted_content' => ['name' => 'Sam Example', 'skills' => ['PHP', 'Laravel']],
        ];
    }
}
