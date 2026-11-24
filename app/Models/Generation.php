<?php

namespace App\Models;

use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'portfolio_id', 'status', 'current_step', 'progress', 'input_snapshot', 'provider', 'model', 'prompt_version', 'usage', 'error_code', 'started_at', 'completed_at'])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['progress' => 'array', 'input_snapshot' => 'array', 'usage' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
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
