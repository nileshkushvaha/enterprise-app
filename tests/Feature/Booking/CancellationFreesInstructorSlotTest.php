<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Contracts\BookingServiceInterface;
use App\Booking\DTOs\CancelBookingData;
use App\Booking\DTOs\MeetingUpdateContext;
use App\Booking\Enums\BookingActor;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\MeetingHostReservationStatus;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Exceptions\SlotUnavailableException;
use App\Booking\Meetings\ManualMeetingProvider;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Booking\Services\AvailabilityService;
use App\Booking\Services\MeetingHostCapacityService;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\MeetingHostReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsZoomHostCapacityFixtures;
use Tests\TestCase;

/**
 * Reported: "after cancelling a 6 PM meeting, booking another lesson at
 * 6 PM still shows the instructor as occupied". The 6 PM MEETING had
 * been cancelled — the video link — not the lesson. A booking blocks
 * its instructor's slot while it is pending or confirmed
 * (Booking::scopeOverlapping → active()); cancelling the lesson is what
 * frees it, and also releases the reserved Zoom host. Replacing only the
 * video meeting keeps the slot, on purpose: the lesson is still on.
 */
final class CancellationFreesInstructorSlotTest extends TestCase
{
    use BuildsZoomHostCapacityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootZoomHostCapacityFixtures();
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: true, defaultProvider: ZoomMeetingProvider::KEY);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function assertSlotBlocked(Booking $booking): void
    {
        try {
            app(AvailabilityService::class)->ensureAvailable($booking->instructor_id, $booking->starts_at->toImmutable(), $booking->ends_at->toImmutable());
            $this->fail('The slot should still be occupied.');
        } catch (SlotUnavailableException) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertSlotFree(Booking $booking): void
    {
        app(AvailabilityService::class)->ensureAvailable($booking->instructor_id, $booking->starts_at->toImmutable(), $booking->ends_at->toImmutable());
        $this->addToAssertionCount(1);
    }

    /** A confirmed demo at 6 PM with a created Zoom meeting and an active host reservation. */
    private function confirmedLessonWithZoomMeeting(): Booking
    {
        $booking = $this->demo($this->teacherA, $this->slot(hour: 18));

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $meeting = BookingMeeting::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame(MeetingStatus::Created, $meeting->status);
        $this->assertSame(ZoomMeetingProvider::KEY, $meeting->provider);
        $this->assertNotNull($this->activeReservationFor($booking));

        return $booking;
    }

    public function test_cancelling_only_the_video_meeting_keeps_the_lesson_and_its_slot(): void
    {
        $booking = $this->confirmedLessonWithZoomMeeting();
        $this->assertSlotBlocked($booking);

        app(BookingMeetingServiceInterface::class)->cancelMeeting($booking);

        $fresh = $booking->fresh();
        $this->assertSame(BookingStatus::Confirmed, $fresh->status, 'the lesson is still on');
        $this->assertSame(MeetingStatus::Cancelled, BookingMeeting::query()->where('booking_id', $booking->id)->sole()->status);
        $this->assertSlotBlocked($fresh);
        $this->assertNotNull($this->activeReservationFor($fresh), 'the Zoom host stays reserved for the lesson');

        // Another student cannot take 6 PM with this instructor.
        $this->expectException(SlotUnavailableException::class);
        $this->demo($this->teacherA, $this->slot(hour: 18), User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]));
    }

    public function test_cancelling_the_lesson_frees_the_slot_and_releases_the_host(): void
    {
        $booking = $this->confirmedLessonWithZoomMeeting();
        $this->assertSlotBlocked($booking);

        app(BookingServiceInterface::class)->cancel($booking, new CancelBookingData(BookingActor::Admin, 'Instructor unavailable'));

        $fresh = $booking->fresh();
        $this->assertSame(BookingStatus::Cancelled, $fresh->status);
        $this->assertSlotFree($fresh);
        $this->assertNull($this->activeReservationFor($fresh));
        $released = MeetingHostReservation::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame(MeetingHostReservationStatus::Released, $released->status);
        $this->assertSame(MeetingHostCapacityService::RELEASE_CANCELLED, $released->release_reason);

        // 6 PM is bookable again with this instructor.
        $again = $this->demo($this->teacherA, $this->slot(hour: 18), User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]));
        $this->assertSame(BookingStatus::Confirmed, $again->status);
    }

    /** Replacing the meeting on another provider for the same lesson never frees the slot in between. */
    public function test_replacing_the_video_meeting_keeps_the_slot_reserved_throughout(): void
    {
        $booking = $this->confirmedLessonWithZoomMeeting();

        app(BookingMeetingServiceInterface::class)->cancelMeeting($booking);
        $this->assertSlotBlocked($booking->fresh());

        $replacement = app(BookingMeetingServiceInterface::class)->saveManualMeeting(
            $booking->fresh(),
            new MeetingUpdateContext(providerLabel: 'manual', joinUrl: 'https://rooms.example.test/replacement'),
        );

        $this->assertSame(MeetingStatus::Created, $replacement?->status);
        $this->assertSame(ManualMeetingProvider::KEY, $replacement->provider);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSlotBlocked($booking->fresh());
    }

    public function test_the_admin_table_labels_the_two_cancellations_distinctly(): void
    {
        $booking = $this->confirmedLessonWithZoomMeeting();

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->assertTableActionVisible('cancel', $booking)
            ->assertTableActionVisible('cancel_meeting', $booking)
            ->assertTableActionHasLabel('cancel', 'Cancel Lesson')
            ->assertTableActionHasLabel('cancel_meeting', 'Cancel Video Meeting Only');
    }
}
