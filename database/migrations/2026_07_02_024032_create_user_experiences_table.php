<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_experiences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('organization_name');
            $table->string('designation');
            $table->string('employment_type', 30);
            $table->string('industry')->nullable();
            $table->string('location')->nullable();

            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->string('city', 100)->nullable();

            $table->text('description')->nullable();
            $table->json('skills')->nullable();
            $table->string('website')->nullable();

            $table->boolean('is_current')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->unsignedSmallInteger('display_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // user_id already indexed by the FK constraint above
            $table->index('display_order');
            $table->index('status');
            $table->index('is_current');
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_experiences');
    }
};
