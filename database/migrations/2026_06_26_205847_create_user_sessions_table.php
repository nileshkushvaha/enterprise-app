<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table): void {
            $table->string('session_id', 191)->primary(); // matches sessions.id
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Device / context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('device_type', 50)->nullable();

            // Activity tracking
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
