<?php

namespace App\Actions;

use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class ValidateCvPdf
{
    public function handle(string $bytes): void
    {
        if (strlen($bytes) > 10 * 1024 * 1024 || ! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -1024), '%%EOF')) {
            throw ValidationException::withMessages(['pdf' => 'Upload a complete PDF of no more than 10 MB.']);
        }
        try {
            $config = new Config;
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(16 * 1024 * 1024);
            $document = (new Parser([], $config))->parseContent($bytes);
            $pages = count($document->getPages());
        } catch (\Exception) {
            throw ValidationException::withMessages(['pdf' => 'This PDF cannot be read. Upload a valid, unencrypted PDF.']);
        }
        if ($pages < 1 || $pages > 20) {
            throw ValidationException::withMessages(['pdf' => 'Your CV must contain between 1 and 20 pages.']);
        }
    }
}
