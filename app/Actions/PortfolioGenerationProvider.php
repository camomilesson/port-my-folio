<?php

namespace App\Actions;

interface PortfolioGenerationProvider
{
    public function extractCv(string $pdfBytes): ProviderResult;

    /** @param array<string, mixed> $evidence */
    public function generateContent(array $evidence): ProviderResult;
}
