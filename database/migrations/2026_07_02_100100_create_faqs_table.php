<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('faq_category_id');
            $table->string('question', 500);
            $table->longText('answer');
            $table->json('audience');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('featured')->default(false);
            $table->string('status', 50)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('faq_category_id')->references('id')->on('faq_categories')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index('faq_category_id');
            $table->index('status');
            $table->index('featured');
            $table->index('published_at');
            $table->index('display_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
