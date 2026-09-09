<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the student asked us to confirm this schedule's future classes
 * from their own balance as they are booked.
 *
 * Default FALSE, and never inferred from anything: paying for a batch
 * once says nothing about consenting to it happening again unattended.
 * This is the only place that consent is recorded, it is per schedule
 * rather than per student, and the student can withdraw it at any time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_series', function (Blueprint $table): void {
            $table->boolean('auto_settle_from_wallet')->default(false)->after('student_timezone');
        });
    }

    public function down(): void
    {
        Schema::table('booking_series', function (Blueprint $table): void {
            $table->dropColumn('auto_settle_from_wallet');
        });
    }
};
