<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scheduler_histories', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->string('triggered_by')->default('scheduler'); // 'scheduler' | 'manual'
            $table->string('status');                             // 'success' | 'failed'
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('output')->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->index(['command', 'ran_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduler_histories');
    }
};
