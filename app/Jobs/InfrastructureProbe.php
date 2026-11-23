<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class InfrastructureProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(public string $token) {}

    public function handle(): void
    {
        Cache::put('infrastructure-probe:'.$this->token, 'completed', now()->addHour());
    }
}
