<?php

namespace App\Console\Commands;

use App\Actions\GeminiPortfolioProvider;
use App\Actions\ProviderFailure;
use Illuminate\Console\Command;

class CheckGeminiIntegration extends Command
{
    protected $signature = 'gemini:check';

    protected $description = 'Extract the fictional bundled CV with Gemini without changing any portfolio';

    public function handle(GeminiPortfolioProvider $provider): int
    {
        try {
            $result = $provider->extractCv(file_get_contents(resource_path('fixtures/demo-cv.pdf')));
        } catch (ProviderFailure $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Gemini PDF extraction and application validation passed.');
        $this->line('Model: '.$result->model);
        $this->line(json_encode($result->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Usage: '.json_encode($result->usage));

        return self::SUCCESS;
    }
}
