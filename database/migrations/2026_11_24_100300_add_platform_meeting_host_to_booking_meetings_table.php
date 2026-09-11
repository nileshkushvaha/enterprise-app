<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which platform host a remote meeting was actually created under. A
 * Zoom meeting belongs to its Zoom user, so once created it must stay
 * on that host: reschedules re-reserve capacity on the SAME host and
 * never move the meeting. Null for Google Meet, manual and legacy rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_meetings', function (Blueprint $table): void {
            $table->foreignUuid('platform_meeting_host_id')->nullable()->after('provider')->constrained('platform_meeting_hosts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_meetings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('platform_meeting_host_id');
        });
    }
};
