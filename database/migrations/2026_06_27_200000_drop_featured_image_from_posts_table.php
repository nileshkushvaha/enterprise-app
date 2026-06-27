<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate any raw paths stored in featured_image to MediaLibrary before dropping.
        // In practice this column was never written to (CreatePost/EditPost unset it before
        // saving), but we guard against any direct DB inserts that may have bypassed the form.
        if (Schema::hasColumn('posts', 'featured_image')) {
            $orphaned = DB::table('posts')
                ->whereNotNull('featured_image')
                ->where('featured_image', '!=', '')
                ->count();

            if ($orphaned > 0) {
                \Illuminate\Support\Facades\Log::warning(
                    "drop_featured_image migration: {$orphaned} post(s) have a raw featured_image path. " .
                    'These rows will lose the path reference. Re-upload featured images via the admin panel.'
                );
            }

            Schema::table('posts', function (Blueprint $table): void {
                $table->dropColumn('featured_image');
            });
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('featured_image')->nullable()->after('excerpt');
        });
    }
};
