<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Merged: make_profile_preference_columns_nullable
//         (timezone/language/date_format/time_format/theme → nullable with updated defaults)
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

            // Localisation preferences (nullable with sensible defaults)
            $table->string('timezone', 80)->nullable()->default('Asia/Kolkata');
            $table->string('language', 10)->nullable()->default('en');
            $table->string('date_format', 20)->nullable()->default('Y-m-d');
            $table->string('time_format', 5)->nullable()->default('H:i');

            // UI preferences
            $table->enum('theme', ['light', 'dark', 'system'])->nullable()->default('dark');

            // Notification preferences
            $table->json('notification_preferences')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
