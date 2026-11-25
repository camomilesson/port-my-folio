<?php

namespace App\Actions;

interface PortfolioGenerationProvider
{
    /** @param array<string, mixed> $evidence */
    public function generateProject(array $evidence): ProviderResult;

    /** @param array<string, mixed> $evidence */
    public function generateProfile(array $evidence): ProviderResult;

    public function extractCv(string $pdfBytes): ProviderResult;

    /** @param array<string, mixed> $evidence */
    public function generateContent(array $evidence): ProviderResult;
}
