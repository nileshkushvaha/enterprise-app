<?php

declare(strict_types=1);

namespace Tests\Feature\Booking\Concurrency;

use App\Booking\Enums\BookingPaymentStatus;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\MeetingHostReservationStatus;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Enums\Weekday;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\BookingType;
use App\Models\MeetingHostReservation;
use App\Models\PlatformMeetingHost;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Models\UserProfile;
use App\Settings\BookingSettings;
use App\Settings\MeetingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Role;

/**
 * Real multi-process races for the one-host Zoom capacity rule, on a
 * real MySQL with real row locks — the property that no sequential
 * test can prove:
 *
 *  1. two DIFFERENT instructors (so two different instructor locks)
 *     book the same hour at the same instant: exactly one wins the
 *     single-capacity host;
 *  2. a verified payment settles a lapsed hold at the same instant the
 *     sweep cancels it: whichever wins, booking and reservation agree;
 *  3. two workers create the same booking's Zoom meeting at once: the
 *     provider is asked exactly once.
 *
 * Reuses tests/Concurrency/run-op.php exactly like the other booking
 * races; fixtures are committed for real so the child processes see them.
 */
class MeetingHostCapacityConcurrencyTest extends ConcurrencyTestCase
{
    private function configure(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $bookings = app(BookingSettings::class);
        $bookings->max_daily_bookings_per_teacher = null;
        $bookings->maximum_advance_booking_days = 3650;
        $bookings->save();

        $settings = app(MeetingSettings::class);
        $settings->meetings_enabled = true;
        $settings->manual_provider_enabled = true;
        $settings->default_provider = ZoomMeetingProvider::KEY;
        // Meetings are created by the ops that test them, never by the
        // BookingConfirmed listener — keeps the booking race about
        // reservations only.
        $settings->create_after_demo_booking_confirmation = false;
        $settings->create_after_paid_booking_confirmation = false;
        $settings->meeting_link_visible_before_minutes = 15;
        $settings->meeting_link_visible_after_minutes = 15;
        $settings->zoom_host_capacity_buffer_minutes = 5;
        $settings->zoom_host_capacity_enabled = true;
        $settings->zoom_enabled = true;
        $settings->zoom_account_id = 'acct_123';
        $settings->zoom_client_id = 'client_abc';
        $settings->zoom_client_secret = Crypt::encryptString('zoom_test_client_secret_value');
        $settings->zoom_host_user_id = 'platform-zoom-host';
        $settings->save();

        PlatformMeetingHost::query()->updateOrCreate(
            ['provider' => ZoomMeetingProvider::KEY, 'host_reference' => 'platform-zoom-host'],
            ['label' => 'Platform Zoom host', 'capacity' => 1, 'is_active' => true],
        );

        BookingType::query()->firstOrCreate(
            ['key' => 'free_demo'],
            ['name' => 'Free Demo', 'duration_minutes' => 30, 'requires_approval' => false, 'is_paid' => false, 'is_active' => true],
        );
    }

    private function teacher(): User
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        UserProfile::updateOrCreate(['user_id' => $teacher->id], ['instructor_status' => 'approved', 'timezone' => 'UTC']);
        TeacherSubject::factory()->state(['teacher_id' => $teacher->id])->subject('maths', 1, 12)->create();

        foreach (Weekday::cases() as $day) {
            TeacherAvailability::factory()->state(['teacher_id' => $teacher->id])->forDay($day)->between('06:00:00', '22:00:00')->create();
        }

        return $teacher;
    }

    private function student(): User
    {
        return User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
    }

    public function test_two_instructors_racing_for_the_single_host_resolve_to_exactly_one_booking(): void
    {
        $this->configure();

        $teacherA = $this->teacher();
        $teacherB = $this->teacher();
        $studentA = $this->student();
        $studentB = $this->student();
        $slot = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0)->toIso8601String();

        $results = $this->race([
            ['book-free-demo-for-host', ['student_id' => $studentA->id, 'instructor_id' => $teacherA->id, 'starts_at' => $slot]],
            ['book-free-demo-for-host', ['student_id' => $studentB->id, 'instructor_id' => $teacherB->id, 'starts_at' => $slot]],
        ]);

        $succeeded = array_values(array_filter($results, fn (array $r): bool => $r['ok']));
        $failed = array_values(array_filter($results, fn (array $r): bool => ! $r['ok']));

        $this->assertCount(1, $succeeded, json_encode($results));
        $this->assertCount(1, $failed, json_encode($results));
        $this->assertSame('App\Booking\Exceptions\MeetingHostCapacityException', $failed[0]['exception'], json_encode($results));

        // The loser's booking rolled back whole: one booking, one active reservation, on the one host.
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame(1, MeetingHostReservation::query()->active()->count());
        $this->assertSame(1, PlatformMeetingHost::query()->count());
    }

    public function test_a_late_payment_and_the_hold_expiry_sweep_leave_booking_and_reservation_consistent(): void
    {
        $this->configure();

        $teacher = $this->teacher();
        $student = $this->student();
        $host = PlatformMeetingHost::query()->sole();
        $paidType = BookingType::factory()->create(['key' => 'paid_race', 'is_paid' => true, 'requires_approval' => false, 'duration_minutes' => 60]);

        // A pending-payment hold whose reserved_until has just lapsed,
        // still holding its host reservation — the moment before the
        // sweep and a verified webhook both arrive. (Fixtures in this
        // class commit for real, so each test uses its own hour.)
        $startsAt = CarbonImmutable::now('UTC')->addDays(3)->setTime(13, 0);
        $booking = Booking::factory()->create([
            'booking_type_id' => $paidType->id,
            'student_id' => $student->id,
            'instructor_id' => $teacher->id,
            'status' => BookingStatus::Pending,
            'payment_status' => BookingPaymentStatus::Pending,
            'price' => '499.00',
            'currency' => 'INR',
            'payment_reference' => 'PAY-HOST-RACE',
            'reserved_until' => now()->subSecond(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'meeting_provider_intent' => ZoomMeetingProvider::KEY,
        ]);
        MeetingHostReservation::query()->create([
            'platform_meeting_host_id' => $host->id,
            'booking_id' => $booking->id,
            'provider' => ZoomMeetingProvider::KEY,
            'lesson_starts_at' => $startsAt,
            'lesson_ends_at' => $startsAt->addHour(),
            'occupies_from' => $startsAt->subMinutes(20),
            'occupies_until' => $startsAt->addHour()->addMinutes(20),
            'status' => MeetingHostReservationStatus::Active,
            'expires_at' => now()->subSecond(),
        ]);

        $results = $this->race([
            ['mark-booking-paid', ['booking_id' => $booking->id, 'reference' => 'PAY-HOST-RACE']],
            ['release-expired-booking-reservations', []],
        ]);

        $booking->refresh();
        $active = MeetingHostReservation::query()->active()->where('booking_id', $booking->id)->count();
        $rows = MeetingHostReservation::query()->where('booking_id', $booking->id)->get();

        $this->assertCount(1, $rows, 'confirmation never adds a second reservation row');

        if ($booking->status === BookingStatus::Confirmed) {
            // Payment won: the hold became a confirmed reservation with no expiry.
            $this->assertSame(BookingPaymentStatus::Paid, $booking->payment_status, json_encode($results));
            $this->assertSame(1, $active, json_encode($results));
            $this->assertNull($rows->first()->expires_at);
        } else {
            // The sweep won: the hold is cancelled, its host released, and
            // the late payment was redirected (late_terminal) — never a
            // confirmed booking without a reservation, never a reservation
            // without a standing booking.
            $this->assertSame(BookingStatus::Cancelled, $booking->status, json_encode($results));
            $this->assertSame(0, $active, json_encode($results));
            $this->assertSame(MeetingHostReservationStatus::Released, $rows->first()->status);
        }
    }

    public function test_two_workers_creating_the_same_zoom_meeting_ask_the_provider_exactly_once(): void
    {
        $this->configure();

        // The booking is built by factory (never through the service), so
        // no listener fires; the explicit creates below still pass the
        // per-kind eligibility gate, which must therefore be on.
        $settings = app(MeetingSettings::class);
        $settings->create_after_demo_booking_confirmation = true;
        $settings->save();

        $teacher = $this->teacher();
        $student = $this->student();
        $startsAt = CarbonImmutable::now('UTC')->addDays(3)->setTime(16, 0); // its own hour — see above
        $demo = BookingType::query()->where('key', 'free_demo')->sole();

        $booking = Booking::factory()->confirmed()->create([
            'booking_type_id' => $demo->id,
            'student_id' => $student->id,
            'instructor_id' => $teacher->id,
            'payment_status' => BookingPaymentStatus::NotRequired,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
            'meeting_provider_intent' => ZoomMeetingProvider::KEY,
        ]);

        $log = tempnam(sys_get_temp_dir(), 'zoom-create-');

        try {
            $results = $this->race([
                ['create-zoom-meeting', ['booking_id' => $booking->id, 'log_path' => $log]],
                ['create-zoom-meeting', ['booking_id' => $booking->id, 'log_path' => $log]],
            ]);

            $this->assertCount(2, array_filter($results, fn (array $r): bool => $r['ok']), json_encode($results));

            $creates = array_values(array_filter(explode(PHP_EOL, (string) file_get_contents($log)), static fn (string $line): bool => $line !== ''));
            $this->assertCount(1, $creates, 'the provider must be asked to create exactly once: '.json_encode($results).' meeting row: '.json_encode(BookingMeeting::query()->where('booking_id', $booking->id)->first()?->only(['status', 'failure_reason', 'metadata'])));
        } finally {
            @unlink($log);
        }

        $meeting = BookingMeeting::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame(MeetingStatus::Created, $meeting->status);
        $this->assertNotNull($meeting->platform_meeting_host_id, 'the meeting records the host it was reserved on');
        $this->assertSame(1, MeetingHostReservation::query()->active()->where('booking_id', $booking->id)->count(), 'reserved once, on the way to the provider');

        // Both workers report the same remote meeting.
        $ids = array_unique(array_map(fn (array $r): ?string => $r['result']['provider_meeting_id'] ?? null, $results));
        $this->assertCount(1, $ids, json_encode($results));
    }
}
