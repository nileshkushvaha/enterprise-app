<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Booking\Contracts\BookingPaymentServiceInterface;
use App\Booking\Contracts\BookingServiceInterface;
use App\Booking\Contracts\ZoomMeetingClient;
use App\Booking\DTOs\CreateBookingData;
use App\Booking\Enums\Weekday;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\MeetingHostReservation;
use App\Models\Payment;
use App\Models\PlatformMeetingHost;
use App\Models\StudentPackageEntitlement;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Models\UserProfile;
use App\Package\Enums\PackageEntitlementStatus;
use App\Settings\BookingSettings;
use App\Settings\MeetingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Fixtures shared by the Zoom host capacity test classes: one platform
 * host, two teachers, a student who can pay, and the booking helpers
 * (demo, paid hold, package-funded, settle a payment) that drive the
 * REAL service layer — never hand-built rows.
 */
trait BuildsZoomHostCapacityFixtures
{
    use CreatesStudentLessonPrices;

    protected User $student;

    protected User $teacherA;

    protected User $teacherB;

    protected FakeZoomMeetingClient $zoom;

    /** Call from setUp(): one host, Zoom as default, reservation ON, no automatic meeting creation. */
    protected function bootZoomHostCapacityFixtures(): void
    {
        Notification::fake();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $this->student = $this->makeStudent();
        $this->teacherA = $this->makeTeacher();
        $this->teacherB = $this->makeTeacher();

        BookingType::query()->firstOrCreate(
            ['key' => 'free_demo'],
            ['name' => 'Free Demo', 'duration_minutes' => 30, 'requires_approval' => false, 'is_paid' => false, 'is_active' => true],
        );
        $priced = $this->createPaidBookingTypeWithPrice('paid_one_to_one', 499.00, 'INR');
        $this->assignBillingCountry($this->student, $priced['country']);

        $bookings = app(BookingSettings::class);
        $bookings->max_daily_bookings_per_teacher = null;
        $bookings->maximum_advance_booking_days = 3650;
        $bookings->save();

        $this->zoom = new FakeZoomMeetingClient;
        $this->app->instance(ZoomMeetingClient::class, $this->zoom);

        $this->configureZoomDefault(capacityEnabled: true, autoCreate: false);
        $this->registerHost();
    }

    protected function makeStudent(): User
    {
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $student->profile()->update([
            'phone_e164' => '+9199999'.str_pad((string) $student->id, 5, '0', STR_PAD_LEFT),
            'phone_verified_at' => now(),
        ]);

        return $student;
    }

    protected function makeTeacher(): User
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        UserProfile::updateOrCreate(['user_id' => $teacher->id], ['instructor_status' => 'approved', 'profile_visibility' => 'public', 'timezone' => 'UTC']);
        TeacherSubject::factory()->state(['teacher_id' => $teacher->id])->subject('maths', 1, 12)->create();

        foreach (Weekday::cases() as $day) {
            TeacherAvailability::factory()->state(['teacher_id' => $teacher->id])->forDay($day)->between('06:00:00', '22:00:00')->create();
        }

        return $teacher;
    }

    protected function configureZoomDefault(bool $capacityEnabled, bool $autoCreate, string $defaultProvider = ZoomMeetingProvider::KEY): MeetingSettings
    {
        $settings = app(MeetingSettings::class);
        $settings->meetings_enabled = true;
        $settings->manual_provider_enabled = true;
        $settings->default_provider = $defaultProvider;
        $settings->create_after_demo_booking_confirmation = $autoCreate;
        $settings->create_after_paid_booking_confirmation = $autoCreate;
        $settings->meeting_link_visible_before_minutes = 15;
        $settings->meeting_link_visible_after_minutes = 15;
        $settings->zoom_host_capacity_buffer_minutes = 5;
        $settings->zoom_host_capacity_enabled = $capacityEnabled;
        $settings->zoom_enabled = true;
        $settings->zoom_account_id = 'acct_123';
        $settings->zoom_client_id = 'client_abc';
        $settings->zoom_client_secret = Crypt::encryptString('zoom_test_client_secret_value');
        $settings->zoom_host_user_id = 'platform-zoom-host';
        $settings->save();

        return $settings;
    }

    protected function registerHost(string $reference = 'platform-zoom-host', int $capacity = 1): PlatformMeetingHost
    {
        $this->artisan('meetings:zoom-hosts:register', ['--host' => $reference, '--capacity' => $capacity])->assertSuccessful();

        return PlatformMeetingHost::query()->where('host_reference', $reference)->firstOrFail();
    }

    protected function slot(int $daysAhead = 3, int $hour = 10, int $minute = 0): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->addDays($daysAhead)->setTime($hour, $minute);
    }

    protected function demo(User $teacher, CarbonImmutable $startsAt, ?User $student = null): Booking
    {
        return app(BookingServiceInterface::class)->request(new CreateBookingData(
            typeKey: 'free_demo',
            studentId: ($student ?? $this->student)->id,
            instructorId: $teacher->id,
            startsAt: $startsAt,
            durationMinutes: 30,
            meta: ['subject' => 'maths', 'grade' => 7],
        ))->refresh();
    }

    protected function paid(User $teacher, CarbonImmutable $startsAt): Booking
    {
        return app(BookingServiceInterface::class)->request(new CreateBookingData(
            typeKey: 'paid_one_to_one',
            studentId: $this->student->id,
            instructorId: $teacher->id,
            startsAt: $startsAt,
            durationMinutes: 60,
            meta: ['subject' => 'maths', 'grade' => 7],
        ))->refresh();
    }

    protected function packageFunded(User $teacher, CarbonImmutable $startsAt): Booking
    {
        $subject = $this->seedLessonSubject('maths');

        $entitlement = StudentPackageEntitlement::withoutEvents(function () use ($teacher, $subject): StudentPackageEntitlement {
            Schema::disableForeignKeyConstraints();
            $entitlement = StudentPackageEntitlement::query()->create([
                'student_id' => $this->student->id,
                'instructor_id' => $teacher->id,
                'proposal_id' => Str::uuid()->toString(),
                'subject_id' => $subject->id,
                'paid_quantity' => 3,
                'bonus_quantity' => 0,
                'total_quantity' => 3,
                'used_quantity' => 0,
                'status' => PackageEntitlementStatus::Active,
                'validity_days' => 365,
                'activated_at' => now()->subDay(),
                'expires_at' => now()->addYear(),
            ]);
            Schema::enableForeignKeyConstraints();

            return $entitlement->refresh();
        });

        return app(BookingServiceInterface::class)->request(new CreateBookingData(
            typeKey: 'paid_one_to_one',
            studentId: $this->student->id,
            instructorId: $teacher->id,
            startsAt: $startsAt,
            durationMinutes: 60,
            meta: ['subject' => 'maths', 'grade' => 7],
            packageEntitlementId: (string) $entitlement->id,
        ))->refresh();
    }

    protected function activeReservations(): int
    {
        return MeetingHostReservation::query()->active()->count();
    }

    protected function activeReservationFor(Booking $booking): ?MeetingHostReservation
    {
        return MeetingHostReservation::query()->active()->where('booking_id', $booking->id)->first();
    }

    /** Settle a pending-payment booking exactly as a verified provider webhook would. */
    protected function settlePayment(Booking $booking): Booking
    {
        app(BookingPaymentServiceInterface::class)->initiate($booking);
        $attempt = Payment::query()->latest('created_at')->firstOrFail();
        $body = (string) json_encode(['event' => 'succeeded', 'reference' => (string) $attempt->idempotency_key]);

        $this->call('POST', '/api/webhooks/bookings/payments/fake', [], [], [], [
            'HTTP_X_BOOKING_PAYMENT_SIGNATURE' => hash_hmac('sha256', $body, (string) config('app.key')),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body)->assertOk();

        return $booking->refresh();
    }
}
