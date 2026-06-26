<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Split name into first/last for enterprise profile
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');

            // Auth security fields
            $table->enum('status', [
                'pending_verification',
                'active',
                'inactive',
                'blocked',
                'suspended',
            ])->default('pending_verification')->change();

            $table->unsignedTinyInteger('failed_login_count')->default(0)->after('remember_token');
            $table->timestamp('locked_until')->nullable()->after('failed_login_count');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->string('last_login_user_agent')->nullable()->after('last_login_ip');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'first_name', 'last_name',
                'failed_login_count', 'locked_until',
                'last_login_at', 'last_login_ip', 'last_login_user_agent',
                'password_changed_at',
            ]);

            // Revert status to original enum
            $table->enum('status', ['active', 'inactive'])->default('active')->change();
        });
    }
};

