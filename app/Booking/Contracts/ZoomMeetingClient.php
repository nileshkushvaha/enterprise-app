<?php

declare(strict_types=1);

namespace App\Booking\Contracts;

use App\Booking\DTOs\ProviderDownloadStream;
use App\Booking\Exceptions\GatewayAmbiguousRequestException;
use App\Booking\Exceptions\GatewayRequestException;
use Carbon\CarbonImmutable;

/**
 * Isolation seam for the Zoom REST API — the only boundary Zoom HTTP
 * traffic may cross. ZoomMeetingProvider never builds a request or
 * touches an access token directly, and tests bind a fake
 * implementation instead of stubbing HTTP. Mirrors GoogleCalendarClient
 * and the Razorpay/Stripe gateway-client seams.
 *
 * Every array returned is pre-sanitized by the implementation: never a
 * raw Zoom API response, never an access token.
 */
interface ZoomMeetingClient
{
    /**
     * Create a scheduled meeting under the given host user (Zoom user id
     * or email).
     *
     * @param  array<string, mixed>  $payload  Zoom create-meeting body (topic, type, start_time, …)
     * @return array{id: string, join_url: ?string, start_url: ?string, password: ?string, timezone: ?string, status: ?string}
     *
     * @throws GatewayRequestException on a definite failure (Zoom answered with an error)
     * @throws GatewayAmbiguousRequestException when the request did not complete and Zoom MAY have created the meeting
     */
    public function createMeeting(string $hostUser, array $payload): array;

    /**
     * Every upcoming meeting under $hostUser whose agenda carries this
     * booking's reference — the reconciliation query for an AMBIGUOUS
     * create (a request that timed out after it may have reached Zoom).
     *
     * Returns ALL matches, never "the first one": adopting an arbitrary
     * match would be a guess. `exhaustive` is false when the bounded
     * page walk stopped before the host's upcoming list was fully read,
     * in which case an empty `matches` proves nothing. Bounded: upcoming
     * meetings only, a fixed maximum number of pages.
     *
     * Endpoints: GET /users/{userId}/meetings?type=upcoming (granular
     * scope meeting:read:list_meetings:admin) and, for a listed meeting
     * that starts at $startsAt but whose list entry carries no agenda,
     * GET /meetings/{meetingId} (meeting:read:meeting:admin — already
     * required by updateMeeting). Matching is on the agenda text SIRI
     * wrote, never on topic or time alone.
     *
     * @return array{matches: list<array{id: string, join_url: ?string, start_url: ?string, password: ?string, timezone: ?string, status: ?string}>, exhaustive: bool}
     *
     * @throws GatewayRequestException
     */
    public function findScheduledMeetings(string $hostUser, string $bookingReference, ?CarbonImmutable $startsAt = null): array;

    /**
     * Identity of one meeting, read without modifying it: host user id
     * and email, agenda, start time. Used to CHECK a meeting before it
     * is adopted for a booking. Null when Zoom reports no such meeting
     * (404). Endpoint: GET /meetings/{meetingId}
     * (granular scope meeting:read:meeting:admin).
     *
     * @return array{id: string, host_id: ?string, host_email: ?string, agenda: ?string, start_time: ?string}|null
     *
     * @throws GatewayRequestException on any other failure
     */
    public function inspectMeeting(string $meetingId): ?array;

    /**
     * Update an existing meeting and return its fresh sanitized state.
     *
     * @param  array<string, mixed>  $payload
     * @return array{id: string, join_url: ?string, start_url: ?string, password: ?string, timezone: ?string, status: ?string}
     *
     * @throws GatewayRequestException
     */
    public function updateMeeting(string $meetingId, array $payload): array;

    /**
     * Delete/cancel a meeting. An already-deleted meeting (404) counts
     * as success — the goal state is "no meeting", not "we deleted it".
     *
     * @throws GatewayRequestException
     */
    public function deleteMeeting(string $meetingId): bool;

    /**
     * The cloud-recording files Zoom holds for one meeting — the
     * reconciliation counterpart of the recording.completed webhook, so
     * a missed or undelivered event never permanently loses a class
     * recording.
     *
     * Returns null when Zoom has no recording for the meeting (404),
     * which is an ordinary "not ready / never recorded" answer rather
     * than a failure. Each file is already sanitized: no download
     * token, no account data, no raw payload.
     *
     * @param  string  $meetingId  the numeric Zoom meeting id, or a UUID for a past occurrence
     * @return array{uuid: ?string, files: list<array{id: string, file_type: ?string, file_extension: ?string, recording_type: ?string, status: ?string, file_size: ?int, recording_start: ?string, recording_end: ?string, download_url: ?string}>}|null
     *
     * @throws GatewayRequestException
     */
    public function listMeetingRecordings(string $meetingId): ?array;

    /**
     * Moves every cloud-recording file of one meeting to Zoom's
     * recoverable TRASH (never a permanent delete). Called only after
     * SIRI holds a verified copy. A meeting with no recordings (404)
     * counts as success — the goal state is "no source left", not "we
     * removed it". Endpoint: DELETE /meetings/{meetingId}/recordings
     * ?action=trash (granular scope cloud_recording:delete:meeting_recording:admin).
     *
     * @throws GatewayRequestException
     */
    public function trashMeetingRecordings(string $meetingId): bool;

    /**
     * Opens an authenticated read stream for one cloud-recording file.
     *
     * $downloadUrl MUST be a Zoom-issued URL — implementations reject
     * any other host, so a value that ever reached the database could
     * not turn this into an arbitrary server-side fetcher. The same
     * rule applies to EVERY redirect hop: implementations follow
     * redirects themselves, refuse any destination that is not HTTPS
     * on an approved provider/CDN host, and never send the bearer
     * token anywhere that failed that check. Redirect count and
     * connect/read timeouts are bounded by configuration.
     *
     * $downloadToken is the short-lived token Zoom includes with a
     * recording webhook (valid ~24h). When absent the implementation
     * falls back to the Server-to-Server access token. Neither is ever
     * persisted or logged.
     *
     * The returned stream carries the response's declared length (when
     * sent) so the staging pump can tell a completed download from a
     * connection that closed early. The caller owns closing it.
     *
     * @throws GatewayRequestException
     */
    public function openRecordingStream(string $downloadUrl, ?string $downloadToken = null): ProviderDownloadStream;

    /**
     * Prove the stored Server-to-Server OAuth credentials can actually
     * mint an access token. The only method an admin-facing validation
     * action may call; never invoked on ordinary page loads. Returns
     * false rather than throwing — validation failure is an expected
     * outcome, not an error path.
     */
    public function validateCredentials(): bool;
}
