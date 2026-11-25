<?php

namespace App\Jobs;

use App\Actions\ProcessGenerationStep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GeneratePortfolioStep implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $generationId, public int $runVersion) {}

    public function handle(ProcessGenerationStep $processor): void
    {
        $processor->handle($this->generationId, $this->runVersion);
    }
}
