<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_category_post', function (Blueprint $table): void {
            $table->uuid('post_id');
            $table->uuid('post_category_id');
            $table->timestamps();

            $table->primary(['post_id', 'post_category_id']);
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreign('post_category_id')->references('id')->on('post_categories')->cascadeOnDelete();
            $table->index(['post_category_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_category_post');
    }
};
