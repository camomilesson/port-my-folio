<?php

namespace Database\Factories;

use App\Models\Generation;
use App\Models\Portfolio;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Generation> */
class GenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'user_id' => fn (array $attributes): int => Portfolio::findOrFail($attributes['portfolio_id'])->user_id,
            'status' => 'queued',
            'input_snapshot' => ['schema_version' => 1, 'focus' => 'Backend roles', 'source' => 'local fixture'],
            'progress' => [],
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => ['status' => 'succeeded', 'current_step' => 'completed', 'provider' => 'fixture', 'model' => 'demo', 'prompt_version' => 'demo-v1', 'started_at' => now()->subMinute(), 'completed_at' => now(), 'usage' => ['input_tokens' => 0, 'output_tokens' => 0]]);
    }
}
