<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Merged: add_name_to_content_blocks_table (name column)
//         add_position_to_content_blocks (position column + index)
//         convert_content_blocks_morph_aliases (no-op on fresh install; morph aliases set in AppServiceProvider)
// Supersedes: create_page_blocks_table + create_post_blocks_table (those tables no longer exist)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Polymorphic owner using morph aliases ('page', 'post') registered in AppServiceProvider
            $table->string('blockable_type');
            $table->uuid('blockable_id');
            $table->index(['blockable_type', 'blockable_id'], 'content_blocks_blockable_index');

            $table->string('block_type');
            $table->string('name')->nullable();
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('position')->default('after_content');
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('block_type');
            $table->index('position');
            $table->index(['blockable_type', 'is_active']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
    }
};
