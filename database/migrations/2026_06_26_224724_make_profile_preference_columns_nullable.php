<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('timezone', 80)->nullable()->default('Asia/Kolkata')->change();
            $table->string('language', 10)->nullable()->default('en')->change();
            $table->string('date_format', 20)->nullable()->default('Y-m-d')->change();
            $table->string('time_format', 5)->nullable()->default('H:i')->change();
            $table->enum('theme', ['light', 'dark', 'system'])->nullable()->default('dark')->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('timezone', 80)->nullable(false)->default('UTC')->change();
            $table->string('language', 10)->nullable(false)->default('en')->change();
            $table->string('date_format', 20)->nullable(false)->default('Y-m-d')->change();
            $table->string('time_format', 5)->nullable(false)->default('24h')->change();
            $table->enum('theme', ['light', 'dark', 'system'])->nullable(false)->default('system')->change();
        });
    }
};
