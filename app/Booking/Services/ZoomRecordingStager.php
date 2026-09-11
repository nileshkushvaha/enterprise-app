<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\Contracts\ZoomMeetingClient;
use App\Booking\DTOs\DiscoveredRecording;
use App\Booking\DTOs\StagedRecordingFile;
use App\Booking\Enums\RecordingFailureCode;
use App\Booking\Exceptions\GatewayRequestException;
use App\Booking\Exceptions\RecordingIngestionException;
use Throwable;

/**
 * Pulls one Zoom cloud-recording file onto the private staging disk.
 *
 * Unlike Google Meet — whose recording already sits in the Drive that
 * SIRI writes to, and can be copied backend-side — a Zoom recording
 * lives in Zoom's cloud. There is no shortcut: it must be downloaded
 * once and uploaded once. This class does the download half; the upload
 * and verification are the shared RecordingStorage pipeline, exactly as
 * for every other provider.
 *
 * Safety properties:
 *
 *  - STREAMED, never buffered. Fixed 1 MB chunks into a staged file, so
 *    a long class never occupies PHP memory. The size ceiling is
 *    enforced by RecordingStagingArea::pump() as bytes arrive — never
 *    trusted from a provider-declared Content-Length, which is used
 *    only to refuse early and to detect a download that ended short.
 *  - NO ARBITRARY URLs. The download URL comes from Zoom's own API for
 *    this lesson's own meeting, and ZoomApiClient validates that the
 *    host is an approved Zoom/CDN host — for the initial URL and for
 *    every redirect hop — before opening a connection or sending the
 *    bearer token. Nothing user- or database-controlled can steer it.
 *  - CLEAN ON FAILURE. A refused hop, an oversized source, a short or
 *    failed write and an incomplete read all abort, and the staged
 *    .part file is removed by the staging area on every failure path.
 *  - NO TOKEN LEAKAGE. The short-lived download credential is used
 *    inside the client and never persisted, returned, or logged.
 */
final class ZoomRecordingStager
{
    public function __construct(
        private readonly ZoomMeetingClient $client,
        private readonly RecordingStagingArea $staging,
    ) {}

    /**
     * @param  string|null  $downloadToken  Zoom's short-lived per-recording token when the
     *                                      artifact came from a webhook; null falls back to
     *                                      the account access token.
     *
     * @throws RecordingIngestionException
     */
    public function stage(DiscoveredRecording $discovered, ?string $downloadToken = null): StagedRecordingFile
    {
        $downloadUrl = $discovered->providerHandle;

        if (blank($downloadUrl)) {
            throw new RecordingIngestionException(
                RecordingFailureCode::SourceDownloadFailed,
                'Zoom recording has no download location.',
            );
        }

        $mimeType = $discovered->mimeType ?? 'video/mp4';

        try {
            $download = $this->client->openRecordingStream($downloadUrl, $downloadToken);
        } catch (GatewayRequestException $e) {
            throw $this->translate($e);
        }

        try {
            return $this->staging->stageStream(
                // The shared pump enforces the size ceiling as bytes
                // arrive, checks every write, and treats a stream that
                // ends short of the declared length as incomplete.
                fn ($handle) => $this->staging->pump($download->stream, $handle, $download->expectedBytes),
                sprintf('zoom-recording.%s', RecordingStagingArea::extensionFor($mimeType, 'recording.mp4')),
                $mimeType,
            );
        } catch (RecordingIngestionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RecordingIngestionException(
                RecordingFailureCode::SourceDownloadFailed,
                'Failed to stage the Zoom recording.',
                previous: $e,
            );
        } finally {
            $download->close();
        }
    }

    /**
     * A 401 here usually means the webhook's download token has aged
     * out (Zoom expires them in about a day) — transient, because the
     * reconciliation sweep re-fetches a fresh URL from the API.
     */
    private function translate(GatewayRequestException $e): RecordingIngestionException
    {
        $message = strtolower($e->getMessage());

        $code = match (true) {
            // A refused destination or an oversized declared length is a
            // property of the source, not a transient condition.
            str_contains($message, 'non-zoom host'),
            str_contains($message, 'size ceiling') => RecordingFailureCode::SourceRejected,
            str_contains($message, 'http 401'),
            str_contains($message, 'http 403') => RecordingFailureCode::SourceAccessDenied,
            str_contains($message, 'http 404'),
            str_contains($message, 'http 410') => RecordingFailureCode::SourceExpired,
            str_contains($message, 'http 429') => RecordingFailureCode::SourceRateLimited,
            default => RecordingFailureCode::SourceDownloadFailed,
        };

        return new RecordingIngestionException($code, $e->getMessage(), previous: $e);
    }
}
