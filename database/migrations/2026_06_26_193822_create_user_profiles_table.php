<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();

            // Personal info
            $table->string('phone', 20)->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('avatar')->nullable();

            // Location
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('postal_code', 20)->nullable();

            // Localisation preferences
            $table->string('timezone', 80)->default('UTC');
            $table->string('language', 10)->default('en');
            $table->string('date_format', 20)->default('Y-m-d');
            $table->string('time_format', 5)->default('24h');

            // UI preferences
            $table->enum('theme', ['light', 'dark', 'system'])->default('system');

            // Notification preferences (stored as bit flags via JSON for flexibility)
            $table->json('notification_preferences')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};

