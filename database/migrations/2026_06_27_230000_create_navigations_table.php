<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location', 50);         // NavigationLocation varchar
            $table->string('layout_type', 50)->default('standard'); // NavigationLayoutType varchar
            $table->string('status', 30)->default('draft');         // NavigationStatus varchar
            $table->text('description')->nullable();
            $table->string('locale', 10)->nullable()->index();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['location', 'status']);
            $table->index(['location', 'locale', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigations');
    }
};
