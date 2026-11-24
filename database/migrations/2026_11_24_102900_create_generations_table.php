<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('queued');
            $table->string('current_step', 64)->nullable();
            $table->json('progress')->nullable();
            $table->json('input_snapshot')->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('prompt_version', 64)->nullable();
            $table->json('usage')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'completed_at']);
            $table->index(['portfolio_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
