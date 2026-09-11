<?php

declare(strict_types=1);

namespace App\Booking\Meetings;

use App\Booking\Contracts\DiscoversRecordingArtifacts;
use App\Booking\Contracts\DisposesSourceRecordings;
use App\Booking\Contracts\MeetingProviderInterface;
use App\Booking\Contracts\MeetingRecordingProviderInterface;
use App\Booking\Contracts\ReconcilesAmbiguousMeetings;
use App\Booking\Contracts\RecordingWebhookProvider;
use App\Booking\Contracts\ZoomMeetingClient;
use App\Booking\DTOs\DiscoveredRecording;
use App\Booking\DTOs\MeetingCancellationResult;
use App\Booking\DTOs\MeetingCreationContext;
use App\Booking\DTOs\MeetingCreationResult;
use App\Booking\DTOs\MeetingUpdateContext;
use App\Booking\DTOs\ProviderRecordingResult;
use App\Booking\DTOs\RecordingWebhookResult;
use App\Booking\DTOs\RemoteMeetingIdentity;
use App\Booking\DTOs\RemoteMeetingReconciliation;
use App\Booking\DTOs\StagedRecordingFile;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Enums\RecordingFailureCode;
use App\Booking\Exceptions\AmbiguousMeetingCreationException;
use App\Booking\Exceptions\BookingException;
use App\Booking\Exceptions\GatewayAmbiguousRequestException;
use App\Booking\Exceptions\InvalidRecordingWebhookException;
use App\Booking\Exceptions\RecordingIngestionException;
use App\Booking\Meetings\Concerns\BuildsSafeMeetingContent;
use App\Booking\Meetings\Concerns\SanitizesProviderMessages;
use App\Booking\Meetings\Concerns\VerifiesZoomWebhooks;
use App\Booking\Services\RecordingEligibilityResolver;
use App\Booking\Services\ZoomRecordingLocator;
use App\Booking\Services\ZoomRecordingStager;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\Recording;
use App\Settings\MeetingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Schedules a Zoom meeting (Server-to-Server OAuth, platform-owned
 * host account) for a confirmed booking. All Zoom HTTP traffic lives
 * behind ZoomMeetingClient — this class never sees a token or a raw
 * API response, only the client's sanitized six-field array.
 *
 * The meeting is created under MeetingSettings::zoom_host_user_id (or
 * zoom_host_email) — the platform's Zoom user, never an instructor's
 * personal account — and is HOSTLESS: that user owns the meeting but
 * never needs to join it (join-before-host, no waiting room, cloud
 * auto-recording; see meetingPayload()). start_url is persisted as
 * BookingMeeting.host_url (hidden from serialization, never shown to
 * students) because it grants host privileges on sight; the join
 * passcode rides the same hidden password column the other providers
 * use.
 *
 * RECORDING. This provider also supplies lesson recordings via Zoom
 * cloud recording, on exactly the same contracts Google Meet uses —
 * see ZoomRecordingLocator for artifact selection and
 * ZoomRecordingStager for the streamed download. Two independent
 * switches govern it and neither implies the other:
 *
 *   zoom_enabled            may SIRI create Zoom meetings at all
 *   zoom_recording_enabled  may Zoom meetings enter the recording flow
 *
 * auto_recording is set per meeting from the full SIRI eligibility
 * chain, so a lesson SIRI considers non-recordable is created with
 * recording off at the provider — consent is enforced at Zoom, not
 * merely after the fact.
 */
final class ZoomMeetingProvider implements DiscoversRecordingArtifacts, DisposesSourceRecordings, MeetingProviderInterface, MeetingRecordingProviderInterface, ReconcilesAmbiguousMeetings, RecordingWebhookProvider
{
    use BuildsSafeMeetingContent;
    use SanitizesProviderMessages;
    use VerifiesZoomWebhooks;

    public const string KEY = 'zoom';

    /** Zoom meeting type 2 = scheduled (never an instant meeting). */
    private const int TYPE_SCHEDULED = 2;

    /**
     * How many minutes before the scheduled start participants may enter
     * the hostless meeting (Zoom `settings.jbh_time`; Zoom offers
     * 0 = anytime, 5, 10, 15). Five is the most conservative bounded
     * option: SIRI hands the link out 15 minutes early, a participant who
     * clicks at once simply waits at Zoom until five minutes before.
     */
    private const int JBH_TIME_MINUTES = 5;

    /** Zoom `settings.approval_type` 2 = no registration required. */
    private const int APPROVAL_NO_REGISTRATION = 2;

    /** Zoom's maximum passcode length. */
    private const int PASSCODE_LENGTH = 10;

    /**
     * The one Zoom event that means "a cloud recording is finished and
     * fetchable". Verified against Zoom's current webhook reference
     * rather than recalled — every other recording event describes a
     * transition we cannot yet ingest.
     */
    private const string EVENT_RECORDING_COMPLETED = 'recording.completed';

    public function __construct(
        private readonly ZoomMeetingClient $client,
        private readonly MeetingSettings $settings,
        private readonly RecordingEligibilityResolver $recordingEligibility,
        private readonly ZoomRecordingLocator $recordings,
        private readonly ZoomRecordingStager $stager,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function isConfigured(): bool
    {
        return $this->settings->zoom_enabled
            && filled($this->settings->zoom_account_id)
            && filled($this->settings->zoom_client_id)
            && $this->settings->decryptedZoomClientSecret() !== null
            && (filled($this->settings->zoom_host_user_id) || filled($this->settings->zoom_host_email));
    }

    public function createMeeting(Booking $booking, MeetingCreationContext $context): MeetingCreationResult
    {
        $startsAt = $context->startsAt ?? $booking->starts_at;
        $endsAt = $context->endsAt ?? $booking->ends_at;
        $timezone = $context->timezone ?? $this->timezoneFor($booking);

        try {
            $meeting = $this->client->createMeeting(
                $this->hostUser($context->hostReference),
                $this->meetingPayload($booking, $startsAt, $endsAt, $timezone, forCreate: true),
            );
        } catch (GatewayAmbiguousRequestException $e) {
            // Zoom may hold a meeting SIRI has no id for. Surfaced as its
            // own type so BookingMeetingService records the ambiguity and
            // reconciles (findExistingMeeting) before ever retrying.
            throw new AmbiguousMeetingCreationException($this->sanitize($e->getMessage()));
        } catch (Throwable $e) {
            throw new BookingException($this->sanitize($e->getMessage()));
        }

        return $this->resultFromMeeting($startsAt, $endsAt, $timezone, $meeting);
    }

    /**
     * Reconciliation for an ambiguous create — a QUERY that reports what
     * Zoom holds for this booking under the host. It never writes.
     *
     * @throws BookingException
     */
    public function findExistingMeeting(Booking $booking, MeetingCreationContext $context): RemoteMeetingReconciliation
    {
        try {
            $search = $this->client->findScheduledMeetings(
                $this->hostUser($context->hostReference),
                $booking->reference,
                $context->startsAt ?? $booking->starts_at,
            );
        } catch (Throwable $e) {
            throw new BookingException($this->sanitize($e->getMessage()));
        }

        $ids = array_values(array_unique(array_map(static fn (array $meeting): string => (string) $meeting['id'], $search['matches'])));

        if (! $search['exhaustive']) {
            return RemoteMeetingReconciliation::truncated($ids);
        }

        return match (count($ids)) {
            0 => RemoteMeetingReconciliation::none(),
            1 => RemoteMeetingReconciliation::found($ids[0]),
            default => RemoteMeetingReconciliation::multiple($ids),
        };
    }

    public function describeExistingMeeting(string $providerMeetingId, MeetingCreationContext $context): ?RemoteMeetingIdentity
    {
        try {
            $meeting = $this->client->inspectMeeting($providerMeetingId);
        } catch (Throwable $e) {
            throw new BookingException($this->sanitize($e->getMessage()));
        }

        if ($meeting === null) {
            return null;
        }

        $startsAt = null;

        if (! empty($meeting['start_time'])) {
            try {
                $startsAt = CarbonImmutable::parse((string) $meeting['start_time'])->utc();
            } catch (Throwable) {
                $startsAt = null;
            }
        }

        return new RemoteMeetingIdentity(
            providerMeetingId: (string) $meeting['id'],
            hostId: $meeting['host_id'] ?? null,
            hostEmail: $meeting['host_email'] ?? null,
            agenda: $meeting['agenda'] ?? null,
            startsAt: $startsAt,
        );
    }

    /**
     * Adopt one identified remote meeting: align it with the booking
     * (PATCH) and read its full state back (GET) — the same call path a
     * reschedule uses, so the adopted meeting ends up exactly as a fresh
     * create would have left it, host credentials included.
     */
    public function adoptExistingMeeting(Booking $booking, string $providerMeetingId, MeetingCreationContext $context): MeetingCreationResult
    {
        $startsAt = $context->startsAt ?? $booking->starts_at;
        $endsAt = $context->endsAt ?? $booking->ends_at;
        $timezone = $context->timezone ?? $this->timezoneFor($booking);

        try {
            $meeting = $this->client->updateMeeting($providerMeetingId, $this->meetingPayload($booking, $startsAt, $endsAt, $timezone));
        } catch (Throwable $e) {
            throw new BookingException($this->sanitize($e->getMessage()));
        }

        return $this->resultFromMeeting($startsAt, $endsAt, $timezone, $meeting);
    }

    public function updateMeeting(BookingMeeting $meeting, MeetingUpdateContext $context): MeetingCreationResult
    {
        $booking = $meeting->booking;
        $startsAt = $context->startsAt ?? $meeting->starts_at;
        $endsAt = $context->endsAt ?? $meeting->ends_at;
        $timezone = $context->timezone ?? $meeting->timezone;

        try {
            $fresh = $meeting->provider_meeting_id !== null
                ? $this->client->updateMeeting($meeting->provider_meeting_id, $this->meetingPayload($booking, $startsAt, $endsAt, $timezone))
                : $this->client->createMeeting($this->hostUser($context->hostReference), $this->meetingPayload($booking, $startsAt, $endsAt, $timezone, forCreate: true));
        } catch (Throwable $e) {
            throw new BookingException($this->sanitize($e->getMessage()));
        }

        return $this->resultFromMeeting($startsAt, $endsAt, $timezone, $fresh);
    }

    public function cancelMeeting(BookingMeeting $meeting): MeetingCancellationResult
    {
        if ($meeting->provider_meeting_id === null) {
            return new MeetingCancellationResult(status: MeetingStatus::Cancelled);
        }

        try {
            $this->client->deleteMeeting($meeting->provider_meeting_id);
        } catch (Throwable $e) {
            return new MeetingCancellationResult(status: MeetingStatus::Failed, failureReason: $this->sanitize($e->getMessage()));
        }

        return new MeetingCancellationResult(status: MeetingStatus::Cancelled);
    }

    // ── RecordingWebhookProvider ──────────────────────────────────────

    public function supportsRecordingWebhooks(): bool
    {
        return $this->settings->zoom_recording_webhooks_enabled
            && $this->supportsRecording()
            && $this->settings->decryptedZoomWebhookSecret() !== null;
    }

    public function verifyRecordingWebhookSignature(Request $request): bool
    {
        return $this->verifyZoomSignature($request, $this->settings->decryptedZoomWebhookSecret());
    }

    public function recordingWebhookChallengeResponse(Request $request): ?array
    {
        return $this->zoomChallengeResponse($request, $this->settings->decryptedZoomWebhookSecret());
    }

    /**
     * Reduces a verified Zoom event to a signal. Deliberately keeps
     * NOTHING from the payload beyond identifiers: the recording files,
     * their download URLs and the short-lived download token are all
     * discarded, and re-fetched server-side at ingestion time.
     */
    public function parseRecordingWebhook(Request $request): RecordingWebhookResult
    {
        $event = $request->input('event');
        $meetingId = $request->input('payload.object.id');
        $uuid = $request->input('payload.object.uuid');
        $eventTs = $request->input('event_ts');

        if (! is_string($event) || $event === '') {
            throw new InvalidRecordingWebhookException('Zoom webhook is missing an event name.');
        }

        if (blank($meetingId) && blank($uuid)) {
            throw new InvalidRecordingWebhookException('Zoom webhook is missing a meeting identifier.');
        }

        // Ownership, defence in depth: the signature already proves the
        // event came through THIS app's subscription, and the meeting id
        // is matched against meetings SIRI created — but an event that
        // names another Zoom account is contradictory and is refused
        // rather than correlated. Absent account_id (older payloads) is
        // tolerated; a present, different one is not.
        $accountId = $request->input('payload.account_id');

        if (is_string($accountId) && $accountId !== '' && filled($this->settings->zoom_account_id) && ! hash_equals((string) $this->settings->zoom_account_id, $accountId)) {
            throw new InvalidRecordingWebhookException('Zoom webhook names a different Zoom account than the one configured.');
        }

        return new RecordingWebhookResult(
            provider: self::KEY,
            // Deterministic for a given delivery, so a Zoom redelivery
            // collides on the unique index instead of re-ingesting.
            eventId: sprintf('%s:%s:%s', $event, $uuid ?? $meetingId, $eventTs ?? 'na'),
            eventType: $event,
            meetingReference: filled($meetingId) ? (string) $meetingId : null,
            // Only a COMPLETED cloud recording is ingestable. Other Zoom
            // recording events (started, stopped, transcript ready) are
            // accepted and ignored — acknowledging them stops Zoom
            // retrying, without pretending there is anything to fetch.
            recordingReady: $event === self::EVENT_RECORDING_COMPLETED,
        );
    }

    // ── DisposesSourceRecordings ──────────────────────────────────────

    /**
     * Zoom keeps the original in its cloud; SIRI's verified copy is the
     * canonical one. With the switch on, the original is moved to the
     * account's recoverable trash — never permanently deleted, and never
     * before RecordingIngestionService has published the SIRI copy.
     */
    public function disposeSourceRecording(Recording $recording): bool
    {
        if (! $this->settings->zoom_recording_trash_source_after_persistence) {
            return false;
        }

        $meetingId = $recording->bookingMeeting?->provider_meeting_id;

        if (blank($meetingId)) {
            throw new RecordingIngestionException(
                RecordingFailureCode::SourceExpired,
                'Zoom recording has no meeting id to dispose of its source by.',
            );
        }

        try {
            return $this->client->trashMeetingRecordings((string) $meetingId);
        } catch (Throwable $e) {
            throw new RecordingIngestionException(
                RecordingFailureCode::SourceDownloadFailed,
                $this->sanitize($e->getMessage()),
                previous: $e,
            );
        }
    }

    // ── MeetingRecordingProviderInterface / DiscoversRecordingArtifacts ──

    /**
     * A configuration declaration, never a network call. False unless
     * Zoom recording is explicitly switched on AND the credentials are
     * present — so a deployment without a Zoom subscription declines
     * recording cleanly instead of failing lessons.
     */
    public function supportsRecording(): bool
    {
        return $this->recordings->isConfigured();
    }

    public function discoverRecording(BookingMeeting $meeting): ?DiscoveredRecording
    {
        return $this->recordings->discover($meeting);
    }

    public function stageRecording(DiscoveredRecording $discovered): StagedRecordingFile
    {
        return $this->stager->stage($discovered);
    }

    /**
     * The base-contract path, composed from the same two steps so there
     * is one implementation of each. A Zoom recording always streams —
     * it lives in Zoom's cloud, not in SIRI's storage backend — so this
     * costs the same as the discovery path and exists only for callers
     * that do not use discovery.
     */
    public function fetchRecording(BookingMeeting $meeting): ?ProviderRecordingResult
    {
        $discovered = $this->discoverRecording($meeting);

        if ($discovered === null) {
            return null;
        }

        return new ProviderRecordingResult(
            providerReference: $discovered->providerReference,
            file: $this->stageRecording($discovered),
            durationSeconds: $discovered->durationSeconds,
            recordedAt: $discovered->recordedAt,
        );
    }

    /** @param  array{id: string, join_url: ?string, start_url: ?string, password: ?string, timezone: ?string, status: ?string}  $meeting */
    private function resultFromMeeting(
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $timezone,
        array $meeting,
    ): MeetingCreationResult {
        // Unlike Google's async conference creation, a successful Zoom
        // create/update always carries join_url — its absence means the
        // response wasn't a meeting at all.
        if (blank($meeting['join_url'])) {
            throw new BookingException('Zoom did not return a join URL for the meeting.');
        }

        return new MeetingCreationResult(
            provider: self::KEY,
            providerMeetingId: $meeting['id'] !== '' ? $meeting['id'] : null,
            providerEventId: null,
            joinUrl: $meeting['join_url'],
            hostUrl: $meeting['start_url'],
            password: $meeting['password'],
            startsAt: $startsAt,
            endsAt: $endsAt,
            timezone: $timezone,
            status: MeetingStatus::Created,
            metadata: array_filter(['zoom_status' => $meeting['status']]),
        );
    }

    /** @return array<string, mixed> */
    /**
     * The Zoom meeting SIRI schedules — HOSTLESS by design (Phase 4).
     *
     * The licensed platform user owns the meeting but never has to join
     * it: participants may enter up to JBH_TIME_MINUTES before the start
     * (`join_before_host` + `jbh_time`), there is no waiting room to be
     * admitted from, and cloud recording starts automatically when the
     * lesson is recording-eligible. Nobody but the platform user holds
     * host rights: no alternative hosts, no co-host assignment, no PMI —
     * every lesson gets its own generated meeting id and its own strong
     * passcode. The instructor and the student are ordinary participants.
     *
     * These are fixed SIRI defaults, not administrator settings: hostless
     * operation must never depend on someone toggling Zoom options. Zoom
     * account-level settings can still LOCK the opposite (waiting room
     * forced on, join-before-host forbidden, authentication required) —
     * the required portal state is listed in
     * docs/deployment/zoom-activation.md §5a and confirmed by inspecting
     * the created meeting on staging.
     *
     * Join-before-host is Zoom infrastructure behaviour only. SIRI's own
     * join window (meeting_link_visible_before_minutes) and lesson
     * authorization decide who is handed the link and when; a link
     * handed out early simply waits at Zoom until jbh_time.
     *
     * $forCreate controls the passcode: it is generated once at creation
     * and deliberately NOT sent on update, so a reschedule or an adoption
     * never rotates a passcode participants already received.
     *
     * @return array<string, mixed>
     */
    private function meetingPayload(Booking $booking, CarbonImmutable $startsAt, CarbonImmutable $endsAt, string $timezone, bool $forCreate = false): array
    {
        $payload = [
            'topic' => $this->safeTitle($booking),
            'type' => self::TYPE_SCHEDULED,
            'start_time' => $startsAt->utc()->format('Y-m-d\TH:i:s\Z'),
            'duration' => max(1, (int) $startsAt->diffInMinutes($endsAt)),
            'timezone' => $timezone,
            'agenda' => $this->safeDescription($booking),
            'settings' => [
                // Hostless entry: participants join without the platform
                // user, from jbh_time minutes before the scheduled start.
                'join_before_host' => true,
                'jbh_time' => self::JBH_TIME_MINUTES,
                'waiting_room' => false,
                // A generated per-lesson id, never the host's Personal
                // Meeting ID (which is reusable and shared across lessons).
                'use_pmi' => false,
                // Nobody is elevated: the teacher is not host, co-host or
                // alternative host, and the student is a participant.
                'alternative_hosts' => '',
                // Participants are invited guests joining by link and
                // passcode; requiring a Zoom sign-in would lock them out.
                'meeting_authentication' => false,
                // No registration step — SIRI is the registration.
                'approval_type' => self::APPROVAL_NO_REGISTRATION,
                'mute_upon_entry' => true,
                // The full SIRI eligibility chain decides, never a
                // hardcode: platform + country + per-provider recording
                // switch + both participants' consent. A lesson SIRI
                // will not record is created with recording OFF at
                // Zoom, so the provider never records something the
                // participants did not agree to.
                //
                // 'cloud' and never 'local': a local recording lands on
                // the host's own computer, outside SIRI's storage,
                // retention and access control entirely. With
                // join_before_host, cloud auto-recording starts when the
                // first participant joins — no host needed.
                'auto_recording' => $this->recordingEligibility->evaluate($booking, $this)->eligible ? 'cloud' : 'none',
                'host_video' => true,
                'participant_video' => false,
            ],
        ];

        if ($forCreate) {
            // Strong per-lesson passcode set by SIRI, so security never
            // depends on the account's "require a passcode" default.
            // Stored on the hidden BookingMeeting.password column; the
            // join_url Zoom returns embeds it when the account allows.
            $payload['password'] = $this->generatePasscode();
        }

        return $payload;
    }

    /**
     * Zoom passcodes: up to 10 characters from [a-z A-Z 0-9 @ - _ *].
     * Letters and digits only here — the widest set every account
     * passcode policy accepts — at the maximum length.
     */
    private function generatePasscode(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $passcode = '';

        for ($i = 0; $i < self::PASSCODE_LENGTH; $i++) {
            $passcode .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $passcode;
    }

    private function timezoneFor(Booking $booking): string
    {
        return $booking->timezone
            ?? $this->settings->zoom_default_timezone
            ?? (string) config('app.timezone', 'UTC');
    }

    /**
     * The Zoom user the meeting is scheduled under. A reserved platform
     * host (context->hostReference, from platform_meeting_hosts) wins;
     * otherwise the single configured host in settings. Never an
     * instructor's or student's identity.
     */
    private function hostUser(?string $reserved = null): string
    {
        if ($reserved !== null && $reserved !== '') {
            return $reserved;
        }

        return $this->settings->zoom_host_user_id
            ?? $this->settings->zoom_host_email
            ?? throw new BookingException('No Zoom host user is configured.');
    }
}
