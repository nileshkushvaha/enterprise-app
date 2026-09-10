<?php

declare(strict_types=1);

namespace App\Booking\Gateways;

use App\Booking\Contracts\ZoomMeetingClient;
use App\Booking\DTOs\ProviderDownloadStream;
use App\Booking\Exceptions\GatewayRequestException;
use App\Settings\MeetingSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Zoom REST API v2 over Laravel's HTTP client — the only class in this
 * codebase that ever talks HTTP to Zoom (no third-party Zoom SDK; none
 * is approved). Server-to-Server OAuth: an account-credentials token is
 * minted with Basic auth (client_id:client_secret) and cached until
 * shortly before expiry.
 *
 * Secret hygiene: the client secret is decrypted per token request and
 * never stored on this class, logged, or echoed into an exception; the
 * access token lives only in the cache entry and the Authorization
 * header. Errors surface as GatewayRequestException with a status code
 * and Zoom's short `message` only — never a request/response dump.
 */
final class ZoomApiClient implements ZoomMeetingClient
{
    private const string TOKEN_URL = 'https://zoom.us/oauth/token';

    private const string API_BASE = 'https://api.zoom.us/v2';

    /** Refresh this many seconds before Zoom's stated expiry. */
    private const int TOKEN_EXPIRY_BUFFER_SECONDS = 60;

    public function __construct(
        private readonly MeetingSettings $settings,
    ) {}

    public function createMeeting(string $hostUser, array $payload): array
    {
        $response = $this->request()->post(
            sprintf('%s/users/%s/meetings', self::API_BASE, rawurlencode($hostUser)),
            $payload,
        );

        if ($response->failed()) {
            throw new GatewayRequestException($this->safeError('create meeting', $response));
        }

        return $this->sanitizeMeeting($response->json() ?? []);
    }

    public function updateMeeting(string $meetingId, array $payload): array
    {
        $patch = $this->request()->patch(
            sprintf('%s/meetings/%s', self::API_BASE, rawurlencode($meetingId)),
            $payload,
        );

        if ($patch->failed()) {
            throw new GatewayRequestException($this->safeError('update meeting', $patch));
        }

        // PATCH answers 204 with no body — re-fetch for the current state.
        $fresh = $this->request()->get(sprintf('%s/meetings/%s', self::API_BASE, rawurlencode($meetingId)));

        if ($fresh->failed()) {
            throw new GatewayRequestException($this->safeError('fetch meeting', $fresh));
        }

        return $this->sanitizeMeeting($fresh->json() ?? []);
    }

    public function deleteMeeting(string $meetingId): bool
    {
        $response = $this->request()->delete(sprintf('%s/meetings/%s', self::API_BASE, rawurlencode($meetingId)));

        // 404 = already gone; the goal state ("no meeting") is reached.
        if ($response->status() === 404) {
            return true;
        }

        if ($response->failed()) {
            throw new GatewayRequestException($this->safeError('delete meeting', $response));
        }

        return true;
    }

    public function listMeetingRecordings(string $meetingId): ?array
    {
        $response = $this->request()->get(sprintf('%s/meetings/%s/recordings', self::API_BASE, rawurlencode($meetingId)));

        // Zoom answers 404 both for "no recording exists" and "meeting
        // unknown". Neither is an error worth failing a lesson over —
        // the sweep simply tries again inside its bounded window.
        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new GatewayRequestException($this->safeError('list meeting recordings', $response));
        }

        return $this->sanitizeRecordings($response->json() ?? []);
    }

    /**
     * Opens the download as a streamed HTTP response and hands back its
     * body as a resource — the recording never passes through PHP
     * memory as a string.
     *
     * Redirects are NOT delegated to the HTTP client. Zoom answers a
     * download with one or more redirects to a signed CDN URL, and an
     * automatic follower would re-send the Authorization header to
     * whatever host the previous hop named. So each hop is requested
     * separately, its destination is validated (HTTPS + approved host)
     * BEFORE any connection is opened, and the bearer token therefore
     * only ever reaches a destination that passed that check. Hops,
     * connect time and read time are all bounded by configuration.
     */
    public function openRecordingStream(string $downloadUrl, ?string $downloadToken = null): ProviderDownloadStream
    {
        // Zoom's own short-lived download token when we have one (it is
        // scoped to that recording), otherwise the account token.
        $token = $downloadToken !== null && $downloadToken !== ''
            ? $downloadToken
            : $this->accessToken();

        $maxRedirects = max(0, (int) config('recordings.zoom.download_max_redirects', 5));
        $ceiling = max(1, (int) config('recordings.max_source_bytes'));
        $url = $downloadUrl;

        for ($hop = 0; ; $hop++) {
            // Every destination, including each redirect target, is
            // checked against the allowlist before a request is built.
            $this->assertZoomDownloadUrl($url);

            try {
                $response = Http::withToken($token)
                    ->withoutRedirecting()
                    ->connectTimeout(max(1, (int) config('recordings.zoom.download_connect_timeout', 15)))
                    ->timeout(max(1, (int) config('recordings.zoom.download_timeout', 900)))
                    ->withOptions([
                        // Bearer header, never a query parameter — a token
                        // in a URL ends up in proxy and access logs.
                        'stream' => true,
                        'read_timeout' => max(1, (int) config('recordings.zoom.download_timeout', 900)),
                    ])
                    ->get($url);
            } catch (Throwable $e) {
                // Transport failure. The URL is not echoed — a signed
                // download URL is a credential.
                throw new GatewayRequestException('Zoom API failed to open the recording download stream (connection error).', 0, $e);
            }

            $status = $response->status();

            if ($status >= 300 && $status < 400) {
                $location = trim((string) $response->header('Location'));
                $response->close();

                if ($hop >= $maxRedirects) {
                    throw new GatewayRequestException(sprintf(
                        'Zoom API failed to download the recording: more than %d redirects.',
                        $maxRedirects,
                    ));
                }

                if ($location === '') {
                    throw new GatewayRequestException('Zoom API failed to download the recording: redirect without a destination.');
                }

                $url = $this->resolveRedirect($url, $location);

                continue;
            }

            if ($status < 200 || $status >= 300) {
                $response->close();

                throw new GatewayRequestException(sprintf('Zoom API failed to download the recording (HTTP %d).', $status));
            }

            $declared = $this->declaredLength($response->header('Content-Length'));

            // Refuse before a single body byte is read — a hostile or
            // corrupt Content-Length must not be allowed to fill the
            // staging disk first and be rejected afterwards. The pump
            // enforces the same ceiling on the bytes that actually
            // arrive, for responses that declare nothing.
            if ($declared !== null && $declared > $ceiling) {
                $response->close();

                throw new GatewayRequestException(sprintf(
                    'Zoom recording of %d bytes exceeds the configured size ceiling of %d bytes.',
                    $declared,
                    $ceiling,
                ));
            }

            $stream = $response->toPsrResponse()->getBody()->detach();

            if (! is_resource($stream)) {
                throw new GatewayRequestException('Zoom API returned a recording response without a readable body.');
            }

            return new ProviderDownloadStream($stream, $declared);
        }
    }

    public function validateCredentials(): bool
    {
        try {
            Cache::forget($this->tokenCacheKey());

            return $this->accessToken() !== '';
        } catch (Throwable) {
            return false;
        }
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson();
    }

    /** @throws GatewayRequestException when a token cannot be minted */
    private function accessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $accountId = (string) $this->settings->zoom_account_id;
        $clientId = (string) $this->settings->zoom_client_id;
        $secret = $this->settings->decryptedZoomClientSecret();

        if ($accountId === '' || $clientId === '' || $secret === null) {
            throw new GatewayRequestException('Zoom credentials are missing or cannot be decrypted.');
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $secret)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'account_credentials',
                'account_id' => $accountId,
            ]);

        if ($response->failed()) {
            throw new GatewayRequestException($this->safeError('mint access token', $response));
        }

        $token = (string) $response->json('access_token', '');
        $expiresIn = (int) $response->json('expires_in', 3600);

        if ($token === '') {
            throw new GatewayRequestException('Zoom token response did not include an access token.');
        }

        Cache::put($this->tokenCacheKey(), $token, max(60, $expiresIn - self::TOKEN_EXPIRY_BUFFER_SECONDS));

        return $token;
    }

    /**
     * The ONLY hosts this client will fetch a recording from — the
     * initial URL AND every redirect destination.
     *
     * Zoom serves recording downloads from its own domains and CDN, so
     * anything else means the URL did not come from Zoom — and a URL
     * that reached us through a webhook payload, a database column or
     * a redirect header must never become an arbitrary outbound
     * request carrying our bearer token. This is the SSRF boundary for
     * recording ingestion. The allowlist is configuration
     * (recordings.zoom.download_hosts), so approving a new CDN host is
     * a reviewed deploy-time change.
     */
    private function assertZoomDownloadUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($parts === false || $scheme !== 'https' || $host === '' || ! $this->isApprovedDownloadHost($host)) {
            // The host is echoed (it is not a secret and is the whole
            // point of the diagnostic); the URL itself is not, since it
            // may carry a token in its query string.
            throw new GatewayRequestException(sprintf(
                'Refused to download a recording from a non-Zoom host [%s].',
                $host !== '' ? $host : 'unknown',
            ));
        }
    }

    private function isApprovedDownloadHost(string $host): bool
    {
        foreach ((array) config('recordings.zoom.download_hosts', []) as $pattern) {
            $pattern = strtolower(trim((string) $pattern));

            if ($pattern === '') {
                continue;
            }

            if (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 1); // ".zoom.us"

                if (strlen($host) > strlen($suffix) && str_ends_with($host, $suffix)) {
                    return true;
                }

                continue;
            }

            if ($host === $pattern) {
                return true;
            }
        }

        return false;
    }

    /** A Location header may be relative; resolve it against the hop that issued it. */
    private function resolveRedirect(string $from, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
            return $location;
        }

        $base = parse_url($from);
        $origin = sprintf('%s://%s', $base['scheme'] ?? 'https', $base['host'] ?? '');

        if (isset($base['port'])) {
            $origin .= ':'.$base['port'];
        }

        if (str_starts_with($location, '//')) {
            return ($base['scheme'] ?? 'https').':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = $base['path'] ?? '/';
        $directory = substr($path, 0, (int) strrpos($path, '/') + 1);

        return $origin.$directory.$location;
    }

    /** Content-Length as a non-negative integer, or null when absent or malformed. */
    private function declaredLength(mixed $header): ?int
    {
        $value = is_array($header) ? ($header[0] ?? null) : $header;
        $value = trim((string) $value);

        return $value !== '' && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * Whitelists the recording fields the application actually uses.
     * Deliberately DROPS `download_token` and every account/host field:
     * a short-lived download credential must never travel further than
     * the call that uses it.
     *
     * @param  array<string, mixed>  $data
     * @return array{uuid: ?string, files: list<array<string, mixed>>}
     */
    private function sanitizeRecordings(array $data): array
    {
        $files = array_map(static fn (array $file): array => [
            'id' => (string) ($file['id'] ?? ''),
            'file_type' => $file['file_type'] ?? null,
            'file_extension' => $file['file_extension'] ?? null,
            'recording_type' => $file['recording_type'] ?? null,
            'status' => $file['status'] ?? null,
            'file_size' => isset($file['file_size']) ? (int) $file['file_size'] : null,
            'recording_start' => $file['recording_start'] ?? null,
            'recording_end' => $file['recording_end'] ?? null,
            'download_url' => $file['download_url'] ?? null,
        ], array_values(array_filter((array) ($data['recording_files'] ?? []), 'is_array')));

        return ['uuid' => isset($data['uuid']) ? (string) $data['uuid'] : null, 'files' => $files];
    }

    /** Keyed on non-secret identifiers only — the secret never feeds a cache key. */
    private function tokenCacheKey(): string
    {
        return 'zoom.s2s_token.'.sha1($this->settings->zoom_account_id.'|'.$this->settings->zoom_client_id);
    }

    /**
     * The whitelist that keeps raw Zoom payloads out of the app: only
     * these six fields ever leave this class.
     *
     * @param  array<string, mixed>  $data
     * @return array{id: string, join_url: ?string, start_url: ?string, password: ?string, timezone: ?string, status: ?string}
     */
    private function sanitizeMeeting(array $data): array
    {
        return [
            'id' => (string) ($data['id'] ?? ''),
            'join_url' => $data['join_url'] ?? null,
            'start_url' => $data['start_url'] ?? null,
            'password' => $data['password'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }

    /**
     * Status code + Zoom's short error text only — never headers,
     * tokens, or a body dump. The REST API reports failures in
     * `message`; the OAuth token endpoint uses `error`/`reason` instead
     * (e.g. "invalid_client: Invalid client_id or client_secret"), so
     * both shapes are read — a token failure must say why, not just
     * "HTTP 401".
     */
    private function safeError(string $action, Response $response): string
    {
        $message = (string) ($response->json('message') ?? '');

        if ($message === '') {
            $message = trim(implode(': ', array_filter([
                (string) ($response->json('error') ?? ''),
                (string) ($response->json('reason') ?? ''),
            ], static fn (string $part): bool => $part !== '')));
        }

        return sprintf(
            'Zoom API failed to %s (HTTP %d)%s',
            $action,
            $response->status(),
            $message !== '' ? ': '.mb_substr($message, 0, 200) : '.',
        );
    }
}
