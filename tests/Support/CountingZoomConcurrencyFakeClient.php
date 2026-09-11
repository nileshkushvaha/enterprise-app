<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Booking\Contracts\ZoomMeetingClient;
use App\Booking\DTOs\ProviderDownloadStream;
use Carbon\CarbonImmutable;

/**
 * A Zoom API double for the multi-process concurrency harness that
 * COUNTS remote meeting creations across process boundaries by
 * appending one line per createMeeting() to a shared file (the same
 * technique as CountingRazorpayConcurrencyFakeClient). The property
 * under test is "the provider was asked to create exactly once"; a
 * database row cannot prove that, because the unique booking_id index
 * would hide the loser's orphaned remote meeting.
 */
final class CountingZoomConcurrencyFakeClient implements ZoomMeetingClient
{
    public const string LOG_ENV = 'CONCURRENCY_ZOOM_CREATE_LOG';

    public function __construct(
        private readonly ?string $logPath = null,
        /** Slow the create so two workers genuinely overlap inside the provider call. */
        private readonly int $createDelayMs = 400,
    ) {}

    public function createMeeting(string $hostUser, array $payload): array
    {
        $id = (string) random_int(100000000, 999999999);
        $this->record($id.' '.$hostUser);

        if ($this->createDelayMs > 0) {
            usleep($this->createDelayMs * 1000);
        }

        return $this->meeting($id, $payload['timezone'] ?? 'UTC');
    }

    public function findScheduledMeetings(string $hostUser, string $bookingReference, ?CarbonImmutable $startsAt = null): array
    {
        return ['matches' => [], 'exhaustive' => true];
    }

    public function inspectMeeting(string $meetingId): ?array
    {
        return null;
    }

    public function updateMeeting(string $meetingId, array $payload): array
    {
        return $this->meeting($meetingId, $payload['timezone'] ?? 'UTC');
    }

    public function deleteMeeting(string $meetingId): bool
    {
        return true;
    }

    public function listMeetingRecordings(string $meetingId): ?array
    {
        return null;
    }

    public function trashMeetingRecordings(string $meetingId): bool
    {
        return true;
    }

    public function openRecordingStream(string $downloadUrl, ?string $downloadToken = null): ProviderDownloadStream
    {
        $stream = fopen('php://memory', 'r+b');

        return new ProviderDownloadStream($stream, 0);
    }

    public function validateCredentials(): bool
    {
        return true;
    }

    /** @return array{id: string, join_url: ?string, start_url: ?string, password: ?string, timezone: ?string, status: ?string} */
    private function meeting(string $id, string $timezone): array
    {
        return [
            'id' => $id,
            'join_url' => 'https://zoom.us/j/'.$id,
            'start_url' => 'https://zoom.us/s/'.$id.'?zak=SENSITIVE-HOST-TOKEN',
            'password' => 'p'.$id,
            'timezone' => $timezone,
            'status' => 'waiting',
        ];
    }

    private function record(string $line): void
    {
        $path = $this->logPath ?? getenv(self::LOG_ENV);

        if (! is_string($path) || $path === '') {
            return;
        }

        file_put_contents($path, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
