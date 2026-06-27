<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_related_post', function (Blueprint $table): void {
            $table->uuid('post_id');
            $table->uuid('related_post_id');
            $table->timestamps();

            $table->primary(['post_id', 'related_post_id']);
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreign('related_post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->index(['related_post_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_related_post');
    }
};

