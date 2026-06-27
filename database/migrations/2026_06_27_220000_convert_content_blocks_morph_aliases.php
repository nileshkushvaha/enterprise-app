<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converts content_blocks.blockable_type from fully-qualified class names
 * to the short morph aliases registered in AppServiceProvider:
 *   App\Models\Page  →  page
 *   App\Models\Post  →  post
 *
 * Fully reversible. Uses chunked updates to handle large datasets safely.
 * Verifies row counts before and after to detect any data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Record counts BEFORE migration for post-migration verification.
        $beforePage = DB::table('content_blocks')
            ->where('blockable_type', 'App\\Models\\Page')
            ->count();

        $beforePost = DB::table('content_blocks')
            ->where('blockable_type', 'App\\Models\\Post')
            ->count();

        // Convert App\Models\Page → page in chunks to avoid locking large tables.
        DB::table('content_blocks')
            ->where('blockable_type', 'App\\Models\\Page')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                DB::table('content_blocks')
                    ->whereIn('id', $rows->pluck('id')->toArray())
                    ->update(['blockable_type' => 'page']);
            });

        // Convert App\Models\Post → post in chunks.
        DB::table('content_blocks')
            ->where('blockable_type', 'App\\Models\\Post')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                DB::table('content_blocks')
                    ->whereIn('id', $rows->pluck('id')->toArray())
                    ->update(['blockable_type' => 'post']);
            });

        // Verify: aliased counts must exactly match pre-migration FQCN counts.
        $afterPage = DB::table('content_blocks')->where('blockable_type', 'page')->count();
        $afterPost = DB::table('content_blocks')->where('blockable_type', 'post')->count();

        if ($beforePage !== $afterPage) {
            throw new \RuntimeException(
                "Morph alias migration: page count mismatch. Before: {$beforePage}, after: {$afterPage}."
            );
        }

        if ($beforePost !== $afterPost) {
            throw new \RuntimeException(
                "Morph alias migration: post count mismatch. Before: {$beforePost}, after: {$afterPost}."
            );
        }
    }

    public function down(): void
    {
        // Record counts BEFORE rollback.
        $beforePage = DB::table('content_blocks')->where('blockable_type', 'page')->count();
        $beforePost = DB::table('content_blocks')->where('blockable_type', 'post')->count();

        // Reverse: page → App\Models\Page
        DB::table('content_blocks')
            ->where('blockable_type', 'page')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                DB::table('content_blocks')
                    ->whereIn('id', $rows->pluck('id')->toArray())
                    ->update(['blockable_type' => 'App\\Models\\Page']);
            });

        // Reverse: post → App\Models\Post
        DB::table('content_blocks')
            ->where('blockable_type', 'post')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                DB::table('content_blocks')
                    ->whereIn('id', $rows->pluck('id')->toArray())
                    ->update(['blockable_type' => 'App\\Models\\Post']);
            });

        // Verify rollback counts match.
        $afterPage = DB::table('content_blocks')->where('blockable_type', 'App\\Models\\Page')->count();
        $afterPost = DB::table('content_blocks')->where('blockable_type', 'App\\Models\\Post')->count();

        if ($beforePage !== $afterPage) {
            throw new \RuntimeException(
                "Morph alias rollback: page count mismatch. Before: {$beforePage}, after: {$afterPage}."
            );
        }

        if ($beforePost !== $afterPost) {
            throw new \RuntimeException(
                "Morph alias rollback: post count mismatch. Before: {$beforePost}, after: {$afterPost}."
            );
        }
    }
};
