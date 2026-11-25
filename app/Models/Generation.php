<?php

namespace App\Models;

use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'portfolio_id', 'status', 'current_step', 'progress', 'input_snapshot', 'provider', 'model', 'prompt_version', 'usage', 'error_code', 'started_at', 'completed_at', 'run_version', 'lease_token', 'lease_expires_at', 'next_run_at'])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    protected $attributes = ['run_version' => 1];

    protected function casts(): array
    {
        return ['progress' => 'array', 'input_snapshot' => 'array', 'usage' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'lease_expires_at' => 'datetime', 'next_run_at' => 'datetime', 'run_version' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
