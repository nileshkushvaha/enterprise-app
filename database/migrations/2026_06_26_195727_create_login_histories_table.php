<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Merged: add_session_id_login_method_to_login_histories_table (session_id, login_method)
//         make_login_histories_user_id_nullable (user_id nullable, FK nullOnDelete)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('status', ['success', 'failed', 'locked', 'unverified', 'blocked'])
                ->default('success');

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Parsed device info (stored for display — populated by service)
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('device_type', 50)->nullable();   // desktop|mobile|tablet|bot

            $table->string('location_country', 100)->nullable();
            $table->string('location_city', 100)->nullable();

            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('login_method', 30)->nullable()->default('password');

            // Index for per-user history queries
            $table->index(['user_id', 'logged_in_at']);
            $table->index(['user_id', 'status']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
