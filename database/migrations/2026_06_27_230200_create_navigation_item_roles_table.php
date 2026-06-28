<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_item_roles', function (Blueprint $table) {
            $table->foreignUuid('navigation_item_id')->constrained('navigation_items')->cascadeOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->primary(['navigation_item_id', 'role_id']);

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_item_roles');
    }
};
