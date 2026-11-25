<?php

namespace App\Actions;

use App\Jobs\GeneratePortfolioStep;
use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

class GenerationState
{
    public static function locked(int $id, Closure $action): mixed
    {
        $record = Generation::find($id);
        if (! $record) {
            return null;
        }

        return DB::transaction(function () use ($record, $action): mixed {
            User::whereKey($record->user_id)->lockForUpdate()->firstOrFail();
            $portfolio = Portfolio::whereKey($record->portfolio_id)->lockForUpdate()->first();
            $generation = Generation::whereKey($record->id)->lockForUpdate()->first();
            if (! $portfolio || ! $generation || $generation->portfolio_id !== $portfolio->id || $generation->user_id !== $portfolio->user_id) {
                return null;
            }

            return $action($generation, $portfolio);
        });
    }

    public static function dispatch(Generation $generation): void
    {
        GeneratePortfolioStep::dispatch($generation->id, $generation->run_version)->delay(min(900, max(0, ($generation->next_run_at?->timestamp ?? now()->timestamp) - now()->timestamp)))->afterCommit();
    }

    public static function fail(Generation $generation, string $code): void
    {
        $generation->update(['status' => 'failed', 'error_code' => $code, 'completed_at' => now(), 'lease_token' => null, 'lease_expires_at' => null, 'next_run_at' => null]);
    }
}
