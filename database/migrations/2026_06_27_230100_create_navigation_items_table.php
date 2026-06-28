<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Merged: add_scheduling_to_navigation_items (locale, publish_from, publish_until + index)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('navigation_id')->constrained('navigations')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('navigation_items')->nullOnDelete();

            // NestedSet columns
            $table->unsignedInteger('_lft')->default(0);
            $table->unsignedInteger('_rgt')->default(0);
            $table->unsignedInteger('depth')->default(0);

            $table->string('label');
            $table->string('link_type', 50);
            $table->string('url')->nullable();
            $table->string('route_name')->nullable();
            $table->json('route_params')->nullable();

            // Polymorphic linkable (page, post, category, tag)
            $table->string('linkable_type')->nullable();
            $table->string('linkable_id')->nullable();

            $table->string('target', 20)->default('_self');
            $table->string('rel')->nullable();
            $table->string('icon')->nullable();
            $table->string('css_class')->nullable();
            $table->string('css_id')->nullable();
            $table->string('badge_text', 50)->nullable();
            $table->string('badge_color', 30)->nullable();
            $table->string('visibility', 30)->default('all');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('open_in_modal')->default(false);
            $table->json('extra_attributes')->nullable();
            $table->string('locale', 10)->nullable();
            $table->timestamp('publish_from')->nullable();
            $table->timestamp('publish_until')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['linkable_type', 'linkable_id']);
            $table->index(['navigation_id', 'is_active', '_lft', '_rgt']);
            $table->index(['navigation_id', 'publish_from', 'publish_until'], 'nav_items_scheduling_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
