<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_blocks', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('block_type')
                ->comment('Optional human-readable label to identify this block in the admin');
        });
    }

    public function down(): void
    {
        Schema::table('content_blocks', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
