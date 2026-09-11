<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Booking\Enums\RecordingFailureCode;
use App\Booking\Enums\RecordingPlaybackState;
use App\Booking\Enums\RecordingStatus;
use App\Booking\Jobs\CaptureLessonRecordingJob;
use App\Booking\Services\RecordingPlaybackAccessResolver;
use App\Booking\Services\RecordingService;
use App\Enums\StudentStatus;
use App\Filament\Resources\Recordings\Pages\ListRecordings;
use App\Filament\Resources\Recordings\Pages\ViewRecording;
use App\Filament\Resources\Recordings\RecordingResource;
use App\Lessons\Enums\LessonOutcome;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Recording;
use App\Models\User;
use App\Settings\FeatureSettings;
use App\Settings\MeetingSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin operations for recordings: read-only visibility plus one
 * audited recovery action.
 *
 * The security assertions matter as much as the functional ones — an
 * admin screen is a place where a storage locator, a credential, or a
 * provider URL could easily leak into a rendered page.
 */
final class RecordingAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string ...$permissions): User
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']));

        foreach ($permissions as $permission) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $admin;
    }

    public function test_an_admin_with_the_permission_can_list_recordings(): void
    {
        $recording = Recording::factory()->available()->create();

        Livewire::actingAs($this->admin('ViewAny:Recording'))
            ->test(ListRecordings::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$recording]);
    }

    /**
     * The locator is how a recording is fetched from Drive or S3 —
     * rendering it on an admin page would put an out-of-band pointer to
     * private student video into a browser and a proxy cache.
     */
    public function test_the_admin_screens_never_render_the_storage_locator_or_credentials(): void
    {
        $recording = Recording::factory()->available()->create([
            'storage_path' => 'recordings/2026/08/lesson-SECRETLOCATOR.mp4',
        ]);

        Livewire::actingAs($this->admin('ViewAny:Recording', 'View:Recording'))
            ->test(ViewRecording::class, ['record' => $recording->getKey()])
            ->assertOk()
            ->assertDontSee('SECRETLOCATOR')
            ->assertDontSee('drive.google.com')
            ->assertDontSee('googleapis.com')
            // The backend name IS shown — it is operationally useful and
            // reveals nothing about the object itself.
            ->assertSee('Storage backend');
    }

    public function test_the_retry_action_returns_a_failed_recording_to_the_pipeline_and_queues_it(): void
    {
        Queue::fake();

        $recording = Recording::factory()->failed()->create();
        $admin = $this->admin('ViewAny:Recording', 'View:Recording', 'Retry:Recording');

        Livewire::actingAs($admin)
            ->test(ListRecordings::class)
            ->callTableAction('retryIngestion', $recording);

        $recording->refresh();

        $this->assertSame(RecordingStatus::Pending, $recording->status);
        $this->assertNull($recording->failure_code);
        $this->assertSame(0, $recording->capture_attempts);

        // Queued, never inline — an admin click must not hold an HTTP
        // request open for the length of a video transfer.
        Queue::assertPushed(CaptureLessonRecordingJob::class);
    }

    public function test_the_retry_action_is_audited_with_the_acting_admin(): void
    {
        Queue::fake();

        $recording = Recording::factory()->failed()->create([
            'failure_code' => RecordingFailureCode::StorageQuotaExceeded,
        ]);
        $admin = $this->admin('ViewAny:Recording', 'View:Recording', 'Retry:Recording');

        Livewire::actingAs($admin)
            ->test(ListRecordings::class)
            ->callTableAction('retryIngestion', $recording);

        $activity = Activity::query()->where('event', 'recording_retry_requested')->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame($recording->getKey(), $activity->subject_id);
        $this->assertSame('storage_quota_exceeded', $activity->properties['previous_failure_code'] ?? null);
    }

    /**
     * A Failed row that still points at a preserved object (publication
     * refused after a meeting replacement) shows the button disabled with
     * the reason, and the service refuses even a direct call — so nothing
     * is queued and the retained locator never changes.
     */
    public function test_the_retry_action_is_disabled_and_refused_for_a_row_whose_object_must_be_preserved(): void
    {
        Queue::fake();

        $recording = Recording::factory()->failed()->create([
            'failure_code' => RecordingFailureCode::MeetingReplacedDuringCapture,
            'storage_driver' => 'filesystem',
            'storage_path' => 'recordings/2026/09/lesson-preserved.mp4',
            'size_bytes' => 4096,
        ]);
        $admin = $this->admin('ViewAny:Recording', 'View:Recording', 'Retry:Recording');

        Livewire::actingAs($admin)
            ->test(ListRecordings::class)
            ->assertTableActionVisible('retryIngestion', $recording)
            ->assertTableActionDisabled('retryIngestion', $recording);

        $this->assertFalse(app(RecordingService::class)->retryFailed($recording, $admin));

        $recording->refresh();
        $this->assertSame(RecordingStatus::Failed, $recording->status);
        $this->assertSame(RecordingFailureCode::MeetingReplacedDuringCapture, $recording->failure_code);
        $this->assertSame('recordings/2026/09/lesson-preserved.mp4', $recording->storage_path);
        Queue::assertNotPushed(CaptureLessonRecordingJob::class);
    }

    public function test_the_retry_action_is_hidden_from_an_admin_without_the_permission(): void
    {
        $recording = Recording::factory()->failed()->create();

        Livewire::actingAs($this->admin('ViewAny:Recording'))
            ->test(ListRecordings::class)
            ->assertTableActionHidden('retryIngestion', $recording);
    }

    /** Only failed recordings are retryable — see RecordingIdempotencyTest for why. */
    public function test_the_retry_action_is_hidden_for_a_recording_that_did_not_fail(): void
    {
        $recording = Recording::factory()->available()->create();

        Livewire::actingAs($this->admin('ViewAny:Recording', 'Retry:Recording'))
            ->test(ListRecordings::class)
            ->assertTableActionHidden('retryIngestion', $recording);
    }

    /**
     * RecordingService is the only writer, and a recording is never
     * administratively deleted — only expired, keeping its metadata.
     */
    public function test_recordings_can_never_be_created_edited_or_deleted_from_the_admin(): void
    {
        $recording = Recording::factory()->available()->create();

        $this->assertFalse(RecordingResource::canCreate());
        $this->assertFalse(RecordingResource::canEdit($recording));
        $this->assertFalse(RecordingResource::canDelete($recording));
        $this->assertFalse(RecordingResource::canDeleteAny());
    }

    // ── Action rendering across states ───────────────────────────────

    /** @return array<string, array{0: string}> */
    public static function nonDownloadableStates(): array
    {
        return [
            'pending' => ['pending'],
            'transferring' => ['transferring'],
            'stored (awaiting verification)' => ['stored'],
            'failed' => ['failed'],
            'expired' => ['expired'],
        ];
    }

    /**
     * Details is always there; Download only when the row is Available
     * with a stored object — decided from the row and the policy, never
     * from a storage or provider call.
     */
    #[DataProvider('nonDownloadableStates')]
    public function test_details_is_always_offered_but_download_only_for_a_playable_recording(string $state): void
    {
        $recording = $state === 'pending' ? Recording::factory()->create() : Recording::factory()->{$state}()->create();
        $available = Recording::factory()->available()->create();
        $admin = $this->admin('ViewAny:Recording', 'View:Recording', 'Retry:Recording', 'Withhold:Recording');

        Livewire::actingAs($admin)
            ->test(ListRecordings::class)
            ->assertSee('Details')
            ->assertTableActionVisible('view', $recording)
            ->assertTableActionVisible('view', $available)
            ->assertTableActionHidden('downloadRecording', $recording)
            ->assertTableActionVisible('downloadRecording', $available);
    }

    public function test_a_failed_row_that_still_holds_a_preserved_object_is_not_downloadable_even_for_a_super_admin(): void
    {
        $recording = Recording::factory()->failed()->create([
            'failure_code' => RecordingFailureCode::MeetingReplacedDuringCapture,
            'storage_driver' => 'filesystem',
            'storage_path' => 'recordings/2026/09/lesson-preserved.mp4',
        ]);
        $super = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $super->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        Livewire::actingAs($super)
            ->test(ListRecordings::class)
            ->assertTableActionHidden('downloadRecording', $recording)
            ->assertTableActionVisible('retryIngestion', $recording)
            ->assertTableActionDisabled('retryIngestion', $recording);

        // The endpoint applies the same business-state rule: policies
        // are bypassed for a super admin, the state check is not.
        $this->actingAs($super)->get(route('admin.recordings.download', $recording))->assertNotFound();
    }

    public function test_a_failed_row_shows_a_concise_failure_label_and_its_attempt_count(): void
    {
        $recording = Recording::factory()->failed()->create([
            'failure_code' => RecordingFailureCode::StorageQuotaExceeded,
            'capture_attempts' => 3,
        ]);

        Livewire::actingAs($this->admin('ViewAny:Recording'))
            ->test(ListRecordings::class)
            ->assertSee('Storage quota exceeded')
            ->assertSee('3')
            ->assertDontSee('Exception')
            ->assertDontSee('googleapis.com');

        $this->assertNotNull($recording);
    }

    // ── Permission differences ───────────────────────────────────────

    public function test_each_secondary_action_needs_its_own_permission(): void
    {
        $failed = Recording::factory()->failed()->create();
        $available = Recording::factory()->available()->create();

        // ViewAny lists rows; metadata (Details) needs View:Recording,
        // exactly as RecordingPolicy::view() says.
        Livewire::actingAs($this->admin('ViewAny:Recording'))
            ->test(ListRecordings::class)
            ->assertCanSeeTableRecords([$failed, $available])
            ->assertTableActionHidden('view', $failed)
            ->assertTableActionHidden('downloadRecording', $available)
            ->assertTableActionHidden('retryIngestion', $failed)
            ->assertTableActionHidden('withholdStudentAccess', $available);

        Livewire::actingAs($this->admin('ViewAny:Recording', 'View:Recording'))
            ->test(ListRecordings::class)
            ->assertTableActionVisible('view', $failed)
            ->assertTableActionVisible('downloadRecording', $available)
            ->assertTableActionHidden('retryIngestion', $failed)
            ->assertTableActionHidden('withholdStudentAccess', $available);

        Livewire::actingAs($this->admin('ViewAny:Recording', 'Withhold:Recording'))
            ->test(ListRecordings::class)
            ->assertTableActionVisible('withholdStudentAccess', $available)
            ->assertTableActionHidden('restoreStudentAccess', $available)
            ->assertTableActionHidden('retryIngestion', $failed);
    }

    // ── Stale UI / crafted requests ──────────────────────────────────

    /**
     * An admin page rendered while the row was Failed, submitted after
     * the sweep already recovered it: the service re-reads under its
     * lock, refuses, nothing is queued and no success is reported.
     */
    public function test_a_retry_submitted_from_a_stale_page_queues_nothing(): void
    {
        Queue::fake();

        $recording = Recording::factory()->failed()->create();
        $admin = $this->admin('ViewAny:Recording', 'View:Recording', 'Retry:Recording');
        $page = Livewire::actingAs($admin)->test(ListRecordings::class);

        $page->assertTableActionVisible('retryIngestion', $recording);

        // Meanwhile: the reconciliation sweep picked the row up again.
        $recording->forceFill(['status' => RecordingStatus::Transferring, 'failure_code' => null, 'transfer_started_at' => now()])->save();

        // The re-rendered table no longer offers the action for the
        // current state, and the submitted request is refused below it.
        $page->assertTableActionHidden('retryIngestion', $recording);

        $this->assertSame(RecordingStatus::Transferring, $recording->refresh()->status);
        Queue::assertNotPushed(CaptureLessonRecordingJob::class);
        // The direct service call — what a crafted request reaches — is refused the same way.
        $this->assertFalse(app(RecordingService::class)->retryFailed($recording, $admin));
    }

    public function test_a_crafted_retry_without_the_permission_is_refused_by_the_service(): void
    {
        Queue::fake();
        $recording = Recording::factory()->failed()->create();

        $this->expectException(AuthorizationException::class);

        try {
            app(RecordingService::class)->retryFailed($recording, $this->admin('ViewAny:Recording', 'View:Recording'));
        } finally {
            $this->assertSame(RecordingStatus::Failed, $recording->refresh()->status);
            Queue::assertNotPushed(CaptureLessonRecordingJob::class);
        }
    }

    // ── Student access is independent of ingestion ───────────────────

    public function test_student_access_can_be_withheld_before_the_recording_exists_and_bites_once_it_is_available(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $features = app(FeatureSettings::class);
        $features->recording_enabled = true;
        $features->save();
        $meetings = app(MeetingSettings::class);
        $meetings->recording_enabled = true;
        $meetings->recording_student_playback_enabled = true;
        $meetings->save();

        $recording = Recording::factory()->create(); // pending — nothing captured yet
        $student = $recording->student;
        // The canonical row and its booking name the same participants.
        $recording->booking->forceFill(['student_id' => $student->id, 'instructor_id' => $recording->teacher_id])->save();
        $student->forceFill(['status' => User::STATUS_ACTIVE])->save();
        $student->assignRole('student');
        $student->profile()->update(['student_status' => StudentStatus::Active]);
        Lesson::factory()->create([
            'booking_id' => $recording->booking_id,
            'student_id' => $student->id,
            'instructor_id' => $recording->teacher_id,
            'outcome' => LessonOutcome::Completed,
            'outcome_finalized_at' => now(),
            'outcome_version' => 1,
        ]);
        $admin = $this->admin('ViewAny:Recording', 'Withhold:Recording');

        Livewire::actingAs($admin)
            ->test(ListRecordings::class)
            ->assertTableActionVisible('withholdStudentAccess', $recording)
            ->assertTableActionHidden('restoreStudentAccess', $recording)
            ->callTableAction('withholdStudentAccess', $recording, ['reason' => 'Parent requested no playback.']);

        $this->assertTrue($recording->refresh()->isStudentAccessWithheld());
        $this->assertSame(RecordingStatus::Pending, $recording->status, 'withholding never touches ingestion');

        // Ingestion completes later.
        $recording->forceFill([
            'status' => RecordingStatus::Available,
            'storage_driver' => 'filesystem',
            'storage_path' => 'recordings/2026/09/lesson-x.mp4',
            'available_at' => now(),
        ])->save();

        $resolver = app(RecordingPlaybackAccessResolver::class);
        $this->assertSame(RecordingPlaybackState::Unavailable, $resolver->stateFor($recording->booking->fresh(), $student->fresh()));

        Livewire::actingAs($admin)
            ->test(ListRecordings::class)
            ->assertTableActionHidden('withholdStudentAccess', $recording)
            ->callTableAction('restoreStudentAccess', $recording);

        $this->assertFalse($recording->refresh()->isStudentAccessWithheld());
        $this->assertSame(RecordingPlaybackState::Available, $resolver->stateFor($recording->booking->fresh(), $student->fresh()), 'restoring returns the recording to the normal policy');
    }

    // ── Details page diagnostics ─────────────────────────────────────

    public function test_the_details_page_explains_a_protected_failure_and_its_next_step_without_backend_detail(): void
    {
        $recording = Recording::factory()->failed()->create([
            'failure_code' => RecordingFailureCode::MeetingReplacedDuringCapture,
            'storage_driver' => 'filesystem',
            'storage_path' => 'recordings/2026/09/lesson-SECRETLOCATOR.mp4',
            'capture_attempts' => 2,
        ]);

        Livewire::actingAs($this->admin('ViewAny:Recording', 'View:Recording', 'Retry:Recording'))
            ->test(ViewRecording::class, ['record' => $recording->getKey()])
            ->assertOk()
            ->assertSee('Operator recovery required')
            ->assertSee('Meeting was replaced while its recording was being captured')
            ->assertSee('(permanent)')
            ->assertSee('Stored object')
            ->assertSee('present')
            ->assertSee('Registered')
            ->assertSee('Failed')
            ->assertDontSee('SECRETLOCATOR')
            ->assertDontSee('Exception');
    }

    public function test_the_details_page_never_implies_a_pending_recording_was_never_attempted(): void
    {
        $recording = Recording::factory()->create(['capture_attempts' => 2]);

        Livewire::actingAs($this->admin('ViewAny:Recording', 'View:Recording'))
            ->test(ViewRecording::class, ['record' => $recording->getKey()])
            ->assertOk()
            ->assertSee('Waiting to retry after 2 attempts')
            ->assertSee('2 of')
            ->assertDontSee('never');

        $fresh = Recording::factory()->create();

        Livewire::actingAs($this->admin('ViewAny:Recording', 'View:Recording'))
            ->test(ViewRecording::class, ['record' => $fresh->getKey()])
            ->assertSee('Queued for capture');
    }

    public function test_the_details_page_distinguishes_stored_awaiting_verification_from_available(): void
    {
        $stored = Recording::factory()->stored()->create();
        $admin = $this->admin('ViewAny:Recording', 'View:Recording');

        Livewire::actingAs($admin)
            ->test(ViewRecording::class, ['record' => $stored->getKey()])
            ->assertSee('awaiting verification')
            ->assertActionHidden('downloadRecording');
    }
}
