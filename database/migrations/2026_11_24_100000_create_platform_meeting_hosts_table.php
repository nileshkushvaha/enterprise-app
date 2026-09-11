<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-owned meeting host identities — the Zoom users SIRI schedules
 * meetings under, and how many meetings each may run at once.
 *
 * A host is an IDENTITY on the platform's single Zoom account, never a
 * credential: the Server-to-Server OAuth client id/secret stay in
 * MeetingSettings (one account, one secret) and every host is addressed
 * through that same token. Adding a licensed host later is one more
 * row, not another secret and not instructor OAuth.
 *
 * `capacity` is the number of simultaneous meetings the licence allows
 * (Zoom Pro: 1). It is data, not code, so a licence upgrade never needs
 * a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_meeting_hosts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 32);
            // The provider's user identity (Zoom user id or email) —
            // exactly what ZoomMeetingClient::createMeeting() takes as
            // the host user. Distinct from every instructor and student.
            $table->string('host_reference', 191);
            $table->string('label', 120)->nullable();
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'host_reference'], 'platform_meeting_hosts_identity_unique');
            $table->index(['provider', 'is_active', 'sort_order'], 'platform_meeting_hosts_pool_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_meeting_hosts');
    }
};
