<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 64);
            $table->string('repository_owner', 100);
            $table->string('repository_name', 100);
            $table->string('author_role', 255);
            $table->text('readme_content')->nullable();
            $table->timestamp('readme_fetched_at')->nullable();
            $table->string('title', 120)->nullable();
            $table->text('summary')->nullable();
            $table->text('overview')->nullable();
            $table->json('technologies')->nullable();
            $table->string('live_url', 2048)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['portfolio_id', 'slug']);
            $table->unique(['portfolio_id', 'repository_owner', 'repository_name']);
            $table->index(['portfolio_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
