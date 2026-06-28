<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('extra_attributes');
            $table->timestamp('publish_from')->nullable()->after('locale');
            $table->timestamp('publish_until')->nullable()->after('publish_from');
            $table->index(
                ['navigation_id', 'publish_from', 'publish_until'],
                'nav_items_scheduling_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropIndex('nav_items_scheduling_index');
            $table->dropColumn(['locale', 'publish_from', 'publish_until']);
        });
    }
};
