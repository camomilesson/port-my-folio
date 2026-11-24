<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('slug', 64);
            $table->text('focus')->nullable();
            $table->string('preset', 32)->default('minimal');
            $table->string('accent_color', 7)->default('#ea580c');
            $table->json('home_content')->nullable();
            $table->json('cv_content')->nullable();
            $table->json('published_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
