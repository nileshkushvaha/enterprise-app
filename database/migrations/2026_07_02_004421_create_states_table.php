<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id');
            $table->string('name');
            $table->string('code', 20)->nullable()->comment('State code (DL, MH, CA, TX)');
            $table->string('iso_code', 20)->nullable()->comment('ISO-3166-2 (IN-DL, US-CA)');
            $table->string('capital')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['country_id', 'code'], 'states_country_code_unique');
            $table->unique(['country_id', 'iso_code'], 'states_country_iso_unique');

            $table->index('country_id', 'states_country_index');
            $table->index('name', 'states_name_index');
            $table->index('status', 'states_status_index');
            $table->index('sort_order', 'states_sort_order_index');

            $table->foreign('country_id', 'states_country_fk')
                ->references('id')->on('countries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
