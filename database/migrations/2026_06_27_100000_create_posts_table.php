<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Merged: drop_featured_image_from_posts_table (column omitted from schema)
//         add_content_to_pages_and_posts (content longText column)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('status')->default('draft');
            $table->string('visibility')->default('private');
            $table->dateTime('published_at')->nullable();
            $table->unsignedSmallInteger('reading_time')->default(1);
            $table->boolean('featured')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->text('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('slug');
            $table->index(['status', 'visibility', 'published_at']);
            $table->index(['author_id', 'status']);
            $table->index(['featured', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
