<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Booking\Contracts\ZoomMeetingClient;
use App\Booking\DTOs\ProviderDownloadStream;
use App\Booking\Exceptions\GatewayAmbiguousRequestException;
use App\Booking\Exceptions\GatewayRequestException;
use Carbon\CarbonImmutable;

/**
 * A Zoom REST API that answers from an in-memory fixture and records
 * what was asked of it — no HTTP, no credentials, no tokens.
 *
 * `recordingFiles` deliberately holds the raw-ish file shape Zoom
 * returns (several MP4 layouts, an M4A, a chat log), because the
 * behaviour most worth testing is that SIRI picks the class VIDEO out
 * of that mixture rather than whatever came first.
 */
final class FakeZoomMeetingClient implements ZoomMeetingClient
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<array{hostUser: string, payload: array<string, mixed>}> */
    public array $created = [];

    /** @var list<array{meetingId: string, payload: array<string, mixed>}> */
    public array $updated = [];

    /** @var list<string> */
    public array $deleted = [];

    /** @var array<string, list<array<string, mixed>>> keyed by meeting id */
    public array $recordingFiles = [];

    /** @var list<array{url: string, token: ?string}> */
    public array $downloads = [];

    public string $downloadBytes = 'zoom recording bytes';

    /**
     * What the fake "response" declares as Content-Length. Null means
     * no declared length; set it above strlen($downloadBytes) to
     * simulate a connection that closed before the body was complete.
     */
    public ?int $declaredDownloadBytes = null;

    public bool $credentialsValid = true;

    public ?GatewayRequestException $throwOnCreate = null;

    /** When throwOnCreate is ambiguous, did Zoom actually create the meeting before the connection dropped? */
    public bool $createRemotelyOnAmbiguousFailure = true;

    public ?GatewayRequestException $throwOnRecordings = null;

    public ?GatewayRequestException $throwOnDownload = null;

    public ?GatewayRequestException $throwOnSearch = null;

    public ?GatewayRequestException $throwOnTrash = null;

    /** @var list<string> meeting ids whose cloud recordings were moved to trash */
    public array $trashed = [];

    /**
     * Remote meetings that exist at Zoom but were NOT created through this
     * fake — an operator-supplied id, another account's meeting, a
     * meeting for a different booking. Keyed by id; each value is the
     * inspectMeeting() shape (id, host_id, host_email, agenda, start_time).
     *
     * @var array<string, array{id: string, host_id: ?string, host_email: ?string, agenda: ?string, start_time: ?string}>
     */
    public array $remoteMeetings = [];

    public ?GatewayRequestException $throwOnUpdate = null;

    /** False simulates a host whose upcoming list is longer than the bounded page walk covers. */
    public bool $searchExhaustive = true;

    /** Meetings with no recording at all — Zoom answers 404, which is not an error. */
    public array $meetingsWithoutRecordings = [];

    public function createMeeting(string $hostUser, array $payload): array
    {
        $this->calls[] = 'createMeeting';

        if ($this->throwOnCreate !== null) {
            $exception = $this->throwOnCreate;
            $this->throwOnCreate = null; // one-shot, like a transient outage

            // An AMBIGUOUS failure means Zoom may have created the
            // meeting: model exactly that by recording it before throwing.
            if ($exception instanceof GatewayAmbiguousRequestException && $this->createRemotelyOnAmbiguousFailure) {
                $this->created[] = ['hostUser' => $hostUser, 'payload' => $payload];
            }

            throw $exception;
        }

        $id = (string) (900000000 + count($this->created));
        $this->created[] = ['hostUser' => $hostUser, 'payload' => $payload];

        return [
            'id' => $id,
            'join_url' => 'https://zoom.us/j/'.$id,
            'start_url' => 'https://zoom.us/s/'.$id.'?zak=SENSITIVE-HOST-TOKEN',
            'password' => 'p'.$id,
            'timezone' => $payload['timezone'] ?? 'UTC',
            'status' => 'waiting',
        ];
    }

    public function findScheduledMeetings(string $hostUser, string $bookingReference, ?CarbonImmutable $startsAt = null): array
    {
        $this->calls[] = 'findScheduledMeetings';

        if ($this->throwOnSearch !== null) {
            throw $this->throwOnSearch;
        }

        $matches = [];

        foreach ($this->created as $index => $created) {
            if ($created['hostUser'] !== $hostUser) {
                continue;
            }

            if (str_contains((string) ($created['payload']['agenda'] ?? ''), 'Booking reference: '.$bookingReference)) {
                $id = (string) (900000000 + $index);
                $matches[] = [
                    'id' => $id,
                    'join_url' => 'https://zoom.us/j/'.$id,
                    'start_url' => null,
                    'password' => null,
                    'timezone' => $created['payload']['timezone'] ?? 'UTC',
                    'status' => 'waiting',
                ];
            }
        }

        return ['matches' => $matches, 'exhaustive' => $this->searchExhaustive];
    }

    public function inspectMeeting(string $meetingId): ?array
    {
        $this->calls[] = 'inspectMeeting';

        if (array_key_exists($meetingId, $this->remoteMeetings)) {
            return $this->remoteMeetings[$meetingId];
        }

        $index = (int) $meetingId - 900000000;

        if (! isset($this->created[$index])) {
            return null;
        }

        $created = $this->created[$index];

        return [
            'id' => $meetingId,
            'host_id' => $created['hostUser'],
            'host_email' => null,
            'agenda' => $created['payload']['agenda'] ?? null,
            'start_time' => $created['payload']['start_time'] ?? null,
        ];
    }

    public function updateMeeting(string $meetingId, array $payload): array
    {
        $this->calls[] = 'updateMeeting';
        $this->updated[] = ['meetingId' => $meetingId, 'payload' => $payload];

        if ($this->throwOnUpdate !== null) {
            throw $this->throwOnUpdate;
        }

        return [
            'id' => $meetingId,
            'join_url' => 'https://zoom.us/j/'.$meetingId,
            'start_url' => 'https://zoom.us/s/'.$meetingId.'?zak=SENSITIVE-HOST-TOKEN',
            'password' => 'p'.$meetingId,
            'timezone' => $payload['timezone'] ?? 'UTC',
            'status' => 'waiting',
        ];
    }

    public function deleteMeeting(string $meetingId): bool
    {
        $this->calls[] = 'deleteMeeting';
        $this->deleted[] = $meetingId;

        return true;
    }

    public function listMeetingRecordings(string $meetingId): ?array
    {
        $this->calls[] = 'listMeetingRecordings';

        if ($this->throwOnRecordings !== null) {
            throw $this->throwOnRecordings;
        }

        if (in_array($meetingId, $this->meetingsWithoutRecordings, true)) {
            return null;
        }

        return ['uuid' => 'uuid-'.$meetingId, 'files' => $this->recordingFiles[$meetingId] ?? []];
    }

    public function trashMeetingRecordings(string $meetingId): bool
    {
        $this->calls[] = 'trashMeetingRecordings';

        if ($this->throwOnTrash !== null) {
            throw $this->throwOnTrash;
        }

        $this->trashed[] = $meetingId;

        return true;
    }

    public function openRecordingStream(string $downloadUrl, ?string $downloadToken = null): ProviderDownloadStream
    {
        $this->calls[] = 'openRecordingStream';

        if ($this->throwOnDownload !== null) {
            throw $this->throwOnDownload;
        }

        $this->downloads[] = ['url' => $downloadUrl, 'token' => $downloadToken];

        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $this->downloadBytes);
        rewind($stream);

        return new ProviderDownloadStream($stream, $this->declaredDownloadBytes);
    }

    public function validateCredentials(): bool
    {
        $this->calls[] = 'validateCredentials';

        return $this->credentialsValid;
    }

    // ── Fixture builders ──────────────────────────────────────────────

    public function withRecordingFile(
        string $meetingId,
        string $id,
        string $fileType,
        ?string $recordingType = null,
        string $status = 'completed',
        ?int $size = 1024,
        ?string $start = null,
        ?string $end = null,
        ?string $downloadUrl = null,
    ): self {
        $this->recordingFiles[$meetingId][] = [
            'id' => $id,
            'file_type' => $fileType,
            'file_extension' => strtoupper($fileType),
            'recording_type' => $recordingType,
            'status' => $status,
            'file_size' => $size,
            'recording_start' => $start,
            'recording_end' => $end,
            'download_url' => $downloadUrl ?? 'https://zoom.us/rec/download/'.$id,
        ];

        return $this;
    }
}
