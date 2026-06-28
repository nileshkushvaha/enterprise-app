<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_item_permissions', function (Blueprint $table) {
            $table->foreignUuid('navigation_item_id')->constrained('navigation_items')->cascadeOnDelete();
            $table->unsignedBigInteger('permission_id');
            $table->primary(['navigation_item_id', 'permission_id']);

            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_item_permissions');
    }
};
