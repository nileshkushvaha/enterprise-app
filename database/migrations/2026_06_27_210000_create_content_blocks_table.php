<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unify page_blocks + post_blocks → content_blocks (polymorphic).
 *
 * blockable_type = 'App\Models\Page'  (was page_blocks.page_id)
 * blockable_type = 'App\Models\Post'  (was post_blocks.post_id)
 *
 * Migration is fully reversible: down() restores both legacy tables
 * from content_blocks data and drops content_blocks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Polymorphic owner — UUID-compatible morph pair
            $table->string('blockable_type');
            $table->uuid('blockable_id');
            $table->index(['blockable_type', 'blockable_id'], 'content_blocks_blockable_index');

            $table->string('block_type');
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Audit fields — nullable; legacy rows will have null
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('block_type');
            $table->index(['blockable_type', 'is_active']);

            $table->timestamps();
            $table->softDeletes();
        });

        // ── Migrate page_blocks ──────────────────────────────────────────
        if (Schema::hasTable('page_blocks')) {
            DB::statement("
                INSERT INTO content_blocks
                    (id, blockable_type, blockable_id, block_type, content, settings,
                     sort_order, is_active, created_by, updated_by,
                     created_at, updated_at, deleted_at)
                SELECT
                    id,
                    'App\\\\Models\\\\Page' AS blockable_type,
                    page_id               AS blockable_id,
                    block_type,
                    content,
                    settings,
                    sort_order,
                    is_active,
                    NULL                  AS created_by,
                    NULL                  AS updated_by,
                    created_at,
                    updated_at,
                    deleted_at
                FROM page_blocks
            ");

            $pageBlockCount  = DB::table('page_blocks')->count();
            $migratedPages   = DB::table('content_blocks')
                ->where('blockable_type', 'App\Models\Page')
                ->count();

            if ($pageBlockCount !== $migratedPages) {
                throw new \RuntimeException(
                    "page_blocks migration mismatch: expected {$pageBlockCount}, got {$migratedPages}"
                );
            }

            Schema::drop('page_blocks');
        }

        // ── Migrate post_blocks ──────────────────────────────────────────
        if (Schema::hasTable('post_blocks')) {
            DB::statement("
                INSERT INTO content_blocks
                    (id, blockable_type, blockable_id, block_type, content, settings,
                     sort_order, is_active, created_by, updated_by,
                     created_at, updated_at, deleted_at)
                SELECT
                    id,
                    'App\\\\Models\\\\Post' AS blockable_type,
                    post_id               AS blockable_id,
                    block_type,
                    content,
                    settings,
                    sort_order,
                    is_active,
                    NULL                  AS created_by,
                    NULL                  AS updated_by,
                    created_at,
                    updated_at,
                    deleted_at
                FROM post_blocks
            ");

            $postBlockCount = DB::table('post_blocks')->count();
            $migratedPosts  = DB::table('content_blocks')
                ->where('blockable_type', 'App\Models\Post')
                ->count();

            if ($postBlockCount !== $migratedPosts) {
                throw new \RuntimeException(
                    "post_blocks migration mismatch: expected {$postBlockCount}, got {$migratedPosts}"
                );
            }

            Schema::drop('post_blocks');
        }
    }

    public function down(): void
    {
        // ── Restore page_blocks ──────────────────────────────────────────
        Schema::create('page_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('page_id');
            $table->string('block_type');
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->index(['page_id', 'sort_order']);
            $table->index('block_type');
            $table->index('is_active');
        });

        DB::statement("
            INSERT INTO page_blocks
                (id, page_id, block_type, content, settings,
                 sort_order, is_active, created_at, updated_at, deleted_at)
            SELECT
                id,
                blockable_id AS page_id,
                block_type, content, settings,
                sort_order, is_active, created_at, updated_at, deleted_at
            FROM content_blocks
            WHERE blockable_type = 'App\\\\Models\\\\Page'
        ");

        // ── Restore post_blocks ──────────────────────────────────────────
        Schema::create('post_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->string('block_type');
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->index(['post_id', 'sort_order']);
            $table->index('block_type');
            $table->index('is_active');
        });

        DB::statement("
            INSERT INTO post_blocks
                (id, post_id, block_type, content, settings,
                 sort_order, is_active, created_at, updated_at, deleted_at)
            SELECT
                id,
                blockable_id AS post_id,
                block_type, content, settings,
                sort_order, is_active, created_at, updated_at, deleted_at
            FROM content_blocks
            WHERE blockable_type = 'App\\\\Models\\\\Post'
        ");

        Schema::drop('content_blocks');
    }
};
