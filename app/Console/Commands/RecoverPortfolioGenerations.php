<?php

namespace App\Console\Commands;

use App\Actions\GenerationState;
use App\Models\Generation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RecoverPortfolioGenerations extends Command
{
    protected $signature = 'generation:recover';

    protected $description = 'Recover expired generation leases and undelivered queued work';

    public function handle(): int
    {
        Generation::whereIn('status', ['queued', 'running'])->whereNotNull('input_snapshot')->orderBy('id')->chunkById(100, function ($records): void {
            foreach ($records as $record) {
                GenerationState::locked($record->id, function (Generation $current) use ($record): void {
                    if (isset($current->progress['deadline_at']) && Carbon::parse($current->progress['deadline_at'])->isPast()) {
                        GenerationState::fail($current, 'recovery_deadline');

                        return;
                    }
                    if ($current->status === 'running' && $current->lease_expires_at?->isPast()) {
                        if (($current->progress['attempts'][$current->current_step] ?? 0) >= 3) {
                            GenerationState::fail($current, 'worker_interrupted');

                            return;
                        }
                        $current->update(['status' => 'queued', 'lease_token' => null, 'lease_expires_at' => null, 'next_run_at' => now()]);
                    }
                    if ($current->status === 'queued' && $current->next_run_at?->lte(now()->subMinute())) {
                        GenerationState::dispatch($current);
                    } elseif ($current->status === 'queued' && $current->next_run_at?->lte(now()) && $record->status === 'running') {
                        GenerationState::dispatch($current);
                    }
                });
            }
        });
        $this->info('Generation recovery checked.');

        return self::SUCCESS;
    }
}
