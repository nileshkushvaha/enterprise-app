<?php

declare(strict_types=1);

namespace Tests\Feature\Student;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $this->student = User::factory()->create(['status' => 'active']);
        $this->student->assignRole('student');
    }

    public function test_notifications_page_returns_200_for_student(): void
    {
        $this->actingAs($this->student)
            ->get(route('dashboard.notifications'))
            ->assertOk();
    }

    public function test_guest_is_redirected_from_notifications_page(): void
    {
        $this->get(route('dashboard.notifications'))->assertRedirect(route('auth.login'));
    }

    public function test_notifications_page_shows_empty_state_when_no_notifications(): void
    {
        $this->actingAs($this->student)
            ->get(route('dashboard.notifications'))
            ->assertOk()
            ->assertSee('No notifications');
    }

    public function test_notifications_page_lists_own_notifications(): void
    {
        $this->createNotification($this->student, 'Test Alert', 'This is a test.');

        $this->actingAs($this->student)
            ->get(route('dashboard.notifications'))
            ->assertOk()
            ->assertSee('Test Alert');
    }

    public function test_notifications_page_does_not_show_other_users_notifications(): void
    {
        $other = User::factory()->create(['status' => 'active']);
        $this->createNotification($other, 'Private Alert', 'Should not be visible.');

        $this->actingAs($this->student)
            ->get(route('dashboard.notifications'))
            ->assertOk()
            ->assertDontSee('Private Alert');
    }

    public function test_mark_read_marks_one_notification_as_read(): void
    {
        $notification = $this->createNotification($this->student, 'Unread', 'body');
        $this->assertNull($notification->read_at);

        $this->actingAs($this->student)
            ->post(route('dashboard.notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_all_unread(): void
    {
        $this->createNotification($this->student, 'Alert 1', 'body');
        $this->createNotification($this->student, 'Alert 2', 'body');

        $this->assertSame(2, $this->student->unreadNotifications()->count());

        $this->actingAs($this->student)
            ->post(route('dashboard.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $this->student->fresh()->unreadNotifications()->count());
    }

    public function test_mark_all_read_does_not_affect_other_users_notifications(): void
    {
        $other = User::factory()->create(['status' => 'active']);
        $otherNotification = $this->createNotification($other, 'Other Alert', 'body');

        $this->actingAs($this->student)
            ->post(route('dashboard.notifications.read-all'));

        $this->assertNull($otherNotification->fresh()->read_at);
    }

    private function createNotification(User $user, string $title, string $body): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => $title, 'message' => $body],
            'read_at' => null,
        ]);
    }
}
