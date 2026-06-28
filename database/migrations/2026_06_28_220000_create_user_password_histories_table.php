<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Future: used by PasswordPolicySettings->prevent_reuse / password_history_count
// When enforcement is enabled, the authentication layer should check this table
// before allowing a password change and reject the last N password_hash values.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_password_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('password_hash');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_password_histories');
    }
};
