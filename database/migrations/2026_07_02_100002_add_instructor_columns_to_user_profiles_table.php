<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false)->after('show_social_links')->index();
            $table->unsignedSmallInteger('featured_order')->nullable()->after('is_featured')->index();
            $table->boolean('is_instructor_verified')->default(false)->after('featured_order');
            $table->enum('instructor_status', ['pending', 'approved', 'rejected', 'published'])->nullable()->after('is_instructor_verified');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn(['is_featured', 'featured_order', 'is_instructor_verified', 'instructor_status']);
        });
    }
};
