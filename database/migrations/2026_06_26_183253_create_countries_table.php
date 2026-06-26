<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->nullable()->unique();
            $table->string('phone_code', 20)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('flag', 10)->nullable()->comment('Emoji or filename');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
