<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->unsignedInteger('run_version')->default(1);
            $table->uuid('lease_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable()->index();
            $table->timestamp('next_run_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->dropColumn(['run_version', 'lease_token', 'lease_expires_at', 'next_run_at']);
        });
    }
};
