<?php

declare(strict_types=1);

namespace Tests\Feature\Student;

use App\Booking\Enums\BookingStatus;
use App\Livewire\Frontend\Student\BookingDetail;
use App\Livewire\Frontend\Student\BookingHistory;
use App\Models\Booking;
use App\Models\BookingAcademicContext;
use App\Models\BookingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole('student');
    }

    public function test_page_renders_the_livewire_component(): void
    {
        $this->actingAs($this->student)
            ->get(route('dashboard.my-bookings'))
            ->assertOk()
            ->assertSeeLivewire(BookingHistory::class);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('dashboard.my-bookings'))->assertRedirect(route('auth.login'));
    }

    public function test_lists_own_bookings_of_any_status(): void
    {
        $type = BookingType::factory()->create(['name' => 'Physics Tutoring']);
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        Booking::factory()->completed()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
        ]);

        Livewire::actingAs($this->student)
            ->test(BookingHistory::class)
            ->assertSee('Physics Tutoring');
    }

    /** Phase 3.1 — a booking with a structured academic snapshot shows the immutable level_display ("Class 10"), never reconstructed from live EducationSystem config. */
    public function test_booking_with_academic_snapshot_shows_the_snapshot_level_display(): void
    {
        $type = BookingType::factory()->create(['key' => 'free_demo', 'name' => 'Free Demo']);
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $booking = Booking::factory()->completed()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
            'meta' => ['subject' => 'maths', 'grade' => 10],
        ]);

        BookingAcademicContext::query()->create([
            'booking_id' => $booking->id,
            'level_term' => 'Class',
            'level_value' => '10',
            'level_display' => 'Class 10',
            'normalized_grade' => 10,
        ]);

        Livewire::actingAs($this->student)
            ->test(BookingDetail::class, ['bookingId' => $booking->id])
            ->assertSee('Class 10')
            ->assertDontSee('Grade 10');
    }

    /** A legacy booking with no academic snapshot keeps the existing "Grade {n}" fallback. */
    public function test_legacy_booking_without_academic_snapshot_shows_the_grade_fallback(): void
    {
        $type = BookingType::factory()->create(['key' => 'free_demo', 'name' => 'Free Demo']);
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $booking = Booking::factory()->completed()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
            'meta' => ['subject' => 'maths', 'grade' => 6],
        ]);

        Livewire::actingAs($this->student)
            ->test(BookingDetail::class, ['bookingId' => $booking->id])
            ->assertSee('Grade 6');
    }

    public function test_status_filter_narrows_results(): void
    {
        $type = BookingType::factory()->create();
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        Booking::factory()->completed()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
        ]);
        Booking::factory()->cancelled()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
        ]);

        Livewire::actingAs($this->student)
            ->test(BookingHistory::class)
            ->set('statusFilter', BookingStatus::Cancelled->value)
            ->assertViewHas('history', fn ($history) => $history->total() === 1
                && $history->first()->status === BookingStatus::Cancelled);
    }

    public function test_list_paginates_and_the_page_size_can_be_changed(): void
    {
        $type = BookingType::factory()->create();
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        Booking::factory()->count(12)->completed()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
        ]);

        $component = Livewire::actingAs($this->student)
            ->test(BookingHistory::class)
            ->assertViewHas('history', fn ($history) => $history->count() === 10 && $history->total() === 12)
            ->assertSee('Showing 1–10 of 12');

        $component->set('perPage', 25)
            ->assertViewHas('history', fn ($history) => $history->count() === 12);
    }

    /** An unlisted page size from the URL falls back to the default rather than being honoured. */
    public function test_an_unlisted_page_size_falls_back_to_the_default(): void
    {
        $type = BookingType::factory()->create();
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        Booking::factory()->count(12)->completed()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
        ]);

        Livewire::actingAs($this->student)
            ->withQueryParams(['per_page' => 500])
            ->test(BookingHistory::class)
            ->assertViewHas('history', fn ($history) => $history->count() === 10);
    }

    public function test_the_detail_page_renders_the_booking(): void
    {
        $type = BookingType::factory()->create(['name' => 'Physics Tutoring']);
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $booking = Booking::factory()->completed()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
        ]);

        $this->actingAs($this->student)
            ->get(route('dashboard.my-bookings.show', $booking))
            ->assertOk()
            ->assertSeeLivewire(BookingDetail::class)
            ->assertSee('Physics Tutoring')
            ->assertSee($booking->reference)
            ->assertSee('Back to My Bookings');
    }

    /** The list carries its filter and page into each row's link… */
    public function test_list_rows_link_to_the_detail_page_carrying_the_current_filter_and_page(): void
    {
        $type = BookingType::factory()->create();
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $bookings = Booking::factory()->count(12)->cancelled()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
        ]);

        Livewire::actingAs($this->student)
            ->test(BookingHistory::class)
            ->set('statusFilter', BookingStatus::Cancelled->value)
            ->call('gotoPage', 2)
            ->assertSeeHtml('status='.BookingStatus::Cancelled->value)
            ->assertSeeHtml('page=2');

        $this->assertNotEmpty($bookings);
    }

    /** …and the detail page's back button returns to exactly that list state. */
    public function test_back_button_returns_to_the_filtered_page_the_student_came_from(): void
    {
        $type = BookingType::factory()->create();
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $booking = Booking::factory()->cancelled()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
        ]);

        $expected = route('dashboard.my-bookings', [
            'status' => BookingStatus::Cancelled->value,
            'per_page' => 25,
            'page' => 2,
        ]);

        $this->actingAs($this->student)
            ->get(route('dashboard.my-bookings.show', [
                'booking' => $booking,
                'status' => BookingStatus::Cancelled->value,
                'per_page' => 25,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSeeHtml(e($expected));
    }

    /** Reaching a booking from elsewhere (Payments, dashboard) still returns to the list the student left. */
    public function test_back_button_falls_back_to_the_last_list_state_when_the_link_carries_none(): void
    {
        $type = BookingType::factory()->create();
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $booking = Booking::factory()->cancelled()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
        ]);

        // The student was last looking at the Cancelled filter…
        Livewire::actingAs($this->student)
            ->test(BookingHistory::class)
            ->set('statusFilter', BookingStatus::Cancelled->value);

        // …then opened this booking from a link with no list state on it.
        $this->actingAs($this->student)
            ->get(route('dashboard.my-bookings.show', $booking))
            ->assertOk()
            ->assertSeeHtml(e(route('dashboard.my-bookings', ['status' => BookingStatus::Cancelled->value])));
    }

    /** A tampered back state is dropped, never echoed into the page. */
    public function test_back_button_ignores_unknown_list_state(): void
    {
        $type = BookingType::factory()->create();
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $booking = Booking::factory()->completed()->create([
            'booking_type_id' => $type->id,
            'student_id' => $this->student->id,
            'instructor_id' => $teacher->id,
        ]);

        $this->actingAs($this->student)
            ->get(route('dashboard.my-bookings.show', [
                'booking' => $booking,
                'status' => 'not-a-status',
                'per_page' => 999,
                'page' => 0,
            ]))
            ->assertOk()
            ->assertSeeHtml(e(route('dashboard.my-bookings')))
            ->assertDontSee('not-a-status')
            ->assertDontSee('per_page=999');
    }

    public function test_does_not_show_other_students_bookings(): void
    {
        $type = BookingType::factory()->create();
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $other = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        Booking::factory()->completed()->create([
            'booking_type_id' => $type->id,
            'student_id' => $other->id,
            'instructor_id' => $teacher->id,
        ]);

        Livewire::actingAs($this->student)
            ->test(BookingHistory::class)
            ->assertSee('No bookings found');
    }
}
