<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The meeting provider a booking was ACCEPTED for. Written at request
 * time (only while Zoom host capacity reservation is enabled), read by
 * BookingMeetingService in preference to the global default — so
 * flipping `default_provider` later can never make an already accepted
 * booking consume a Zoom host nobody reserved, nor silently move a
 * booking off the provider whose capacity it holds. Null means "decide
 * from the default at creation time" (pre-existing behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('meeting_provider_intent', 32)->nullable()->after('meeting_url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['meeting_provider_intent']);
            $table->dropColumn('meeting_provider_intent');
        });
    }
};
