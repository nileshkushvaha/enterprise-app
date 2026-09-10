<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\ZoomMeetingClient;
use App\Booking\Exceptions\GatewayRequestException;
use App\Booking\Gateways\ZoomApiClient;
use App\Settings\MeetingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ZoomApiClientTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'zoom_test_client_secret_value';

    private const TOKEN = 'zoom_access_token_AAAABBBBCCCCDDDD';

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(MeetingSettings::class);
        $settings->zoom_enabled = true;
        $settings->zoom_account_id = 'acct_123';
        $settings->zoom_client_id = 'client_abc';
        $settings->zoom_client_secret = Crypt::encryptString(self::SECRET);
        $settings->zoom_host_user_id = 'host-user-1';
        $settings->save();
    }

    private function client(): ZoomApiClient
    {
        return app(ZoomApiClient::class);
    }

    /** @param  array<string, mixed>  $meeting */
    private function fakeZoom(array $meeting = [], int $meetingStatus = 201): void
    {
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => self::TOKEN, 'expires_in' => 3600]),
            'api.zoom.us/v2/users/*/meetings' => Http::response($meeting !== [] ? $meeting : [
                'id' => 987654321,
                'join_url' => 'https://zoom.us/j/987654321',
                'start_url' => 'https://zoom.us/s/987654321?zak=host-token',
                'password' => 'p4ss',
                'timezone' => 'Asia/Kolkata',
                'status' => 'waiting',
                'instructor_id' => 'raw-host-id-should-never-leak',
                'settings' => ['alternative_hosts' => 'internal@example.com'],
            ], $meetingStatus),
        ]);
    }

    public function test_token_is_requested_with_basic_auth_and_account_credentials_grant(): void
    {
        $this->fakeZoom();

        $this->client()->createMeeting('host-user-1', ['topic' => 'Lesson']);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'zoom.us/oauth/token')) {
                return false;
            }

            $expected = 'Basic '.base64_encode('client_abc:'.self::SECRET);

            return $request->hasHeader('Authorization', $expected)
                && str_contains($request->body(), 'grant_type=account_credentials')
                && str_contains($request->body(), 'account_id=acct_123');
        });

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/v2/users/host-user-1/meetings')
            && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN));
    }

    public function test_token_is_cached_across_api_calls(): void
    {
        $this->fakeZoom();

        $this->client()->createMeeting('host-user-1', ['topic' => 'Lesson A']);
        $this->client()->createMeeting('host-user-1', ['topic' => 'Lesson B']);

        Http::assertSentCount(3); // 1 token + 2 meeting creates — never a second token mint.
    }

    public function test_create_meeting_returns_only_sanitized_whitelisted_fields(): void
    {
        $this->fakeZoom();

        $meeting = $this->client()->createMeeting('host-user-1', ['topic' => 'Lesson']);

        $this->assertSame(
            ['id', 'join_url', 'start_url', 'password', 'timezone', 'status'],
            array_keys($meeting),
        );
        $this->assertSame('987654321', $meeting['id']);
        $this->assertSame('https://zoom.us/j/987654321', $meeting['join_url']);
        // The raw response's instructor_id / settings never cross the boundary.
        $this->assertStringNotContainsString('raw-host-id-should-never-leak', json_encode($meeting));
    }

    public function test_api_failure_throws_safe_exception_without_secret_or_token(): void
    {
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => self::TOKEN, 'expires_in' => 3600]),
            'api.zoom.us/v2/users/*/meetings' => Http::response(['code' => 1001, 'message' => 'User does not exist.'], 404),
        ]);

        try {
            $this->client()->createMeeting('missing-user', ['topic' => 'Lesson']);
            $this->fail('Expected a GatewayRequestException.');
        } catch (GatewayRequestException $e) {
            $this->assertStringContainsString('HTTP 404', $e->getMessage());
            $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
        }
    }

    public function test_token_mint_failure_throws_safe_exception_carrying_zooms_error_and_reason(): void
    {
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['reason' => 'Invalid client', 'error' => 'invalid_client'], 401),
        ]);

        try {
            $this->client()->createMeeting('host-user-1', ['topic' => 'Lesson']);
            $this->fail('Expected a GatewayRequestException.');
        } catch (GatewayRequestException $e) {
            // The OAuth endpoint reports failures in error/reason (not
            // message) — an admin reading this must see why the token
            // was refused, not a bare "HTTP 401".
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
            $this->assertStringContainsString('invalid_client', $e->getMessage());
            $this->assertStringContainsString('Invalid client', $e->getMessage());
            $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
        }
    }

    public function test_client_never_logs_token_or_secret(): void
    {
        Log::spy();
        $this->fakeZoom();

        $this->client()->createMeeting('host-user-1', ['topic' => 'Lesson']);

        foreach (['debug', 'info', 'warning', 'error'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }

    public function test_update_meeting_patches_then_returns_fresh_sanitized_state(): void
    {
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => self::TOKEN, 'expires_in' => 3600]),
            'api.zoom.us/v2/meetings/987654321' => Http::sequence()
                ->push(null, 204) // PATCH
                ->push([          // follow-up GET
                    'id' => 987654321,
                    'join_url' => 'https://zoom.us/j/987654321',
                    'start_url' => 'https://zoom.us/s/987654321',
                    'password' => 'p4ss',
                    'timezone' => 'UTC',
                    'status' => 'waiting',
                ]),
        ]);

        $meeting = $this->client()->updateMeeting('987654321', ['topic' => 'Lesson (rescheduled)']);

        $this->assertSame('987654321', $meeting['id']);
        $this->assertSame('https://zoom.us/j/987654321', $meeting['join_url']);
    }

    public function test_delete_meeting_treats_404_as_success(): void
    {
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => self::TOKEN, 'expires_in' => 3600]),
            'api.zoom.us/v2/meetings/*' => Http::response(['message' => 'Meeting not found.'], 404),
        ]);

        $this->assertTrue($this->client()->deleteMeeting('gone-already'));
    }

    public function test_validate_credentials_true_on_token_success(): void
    {
        Http::fake(['zoom.us/oauth/token*' => Http::response(['access_token' => self::TOKEN, 'expires_in' => 3600])]);

        $this->assertTrue($this->client()->validateCredentials());
    }

    public function test_validate_credentials_false_on_token_failure(): void
    {
        Http::fake(['zoom.us/oauth/token*' => Http::response(['error' => 'invalid_client'], 401)]);

        $this->assertFalse($this->client()->validateCredentials());
    }

    public function test_container_binds_contract_to_this_client(): void
    {
        $this->assertInstanceOf(ZoomApiClient::class, app(ZoomMeetingClient::class));
    }

    // ── Recording download: redirect and transfer hardening ───────────

    private const DOWNLOAD_URL = 'https://zoom.us/rec/download/abc123';

    private function mp4Bytes(): string
    {
        return "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41".str_repeat("\x00", 200);
    }

    /** @param  array<string, mixed>  $responses  extra URL-pattern → response pairs */
    private function fakeDownload(array $responses): void
    {
        Http::fake(array_merge([
            'zoom.us/oauth/token*' => Http::response(['access_token' => self::TOKEN, 'expires_in' => 3600]),
        ], $responses));
    }

    public function test_a_recording_download_follows_a_redirect_to_an_approved_zoom_cdn_host_and_streams_the_body(): void
    {
        $bytes = $this->mp4Bytes();
        $this->fakeDownload([
            'zoom.us/rec/download/*' => Http::response('', 302, ['Location' => 'https://us06web.zoom.us/rec/cdn/abc123?sig=SIGNED']),
            'us06web.zoom.us/rec/cdn/*' => Http::response($bytes, 200, ['Content-Length' => (string) strlen($bytes)]),
        ]);

        $download = $this->client()->openRecordingStream(self::DOWNLOAD_URL);

        $this->assertSame(strlen($bytes), $download->expectedBytes);
        $this->assertSame($bytes, stream_get_contents($download->stream));
        $download->close();

        // Both hops carried the bearer token — both are approved hosts.
        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://zoom.us/rec/download/') && $r->hasHeader('Authorization', 'Bearer '.self::TOKEN));
        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://us06web.zoom.us/rec/cdn/') && $r->hasHeader('Authorization', 'Bearer '.self::TOKEN));
    }

    /** A webhook's short-lived token takes precedence over the account token and is never minted. */
    public function test_a_supplied_download_token_is_used_instead_of_minting_an_account_token(): void
    {
        $this->fakeDownload([
            'zoom.us/rec/download/*' => Http::response($this->mp4Bytes(), 200),
        ]);

        $this->client()->openRecordingStream(self::DOWNLOAD_URL, 'WEBHOOK-DOWNLOAD-TOKEN')->close();

        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://zoom.us/rec/download/') && $r->hasHeader('Authorization', 'Bearer WEBHOOK-DOWNLOAD-TOKEN'));
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'oauth/token'));
    }

    /**
     * THE redirect test. Zoom's first hop is legitimate; the redirect
     * points somewhere that is not Zoom. The transfer must stop, and —
     * more importantly — no request of any kind, let alone one carrying
     * the bearer token, may ever be made to that destination.
     */
    public function test_a_redirect_to_an_unapproved_host_is_refused_and_never_receives_the_bearer_token(): void
    {
        $this->fakeDownload([
            'zoom.us/rec/download/*' => Http::response('', 302, ['Location' => 'https://evil.example.com/collect?next=1']),
            'evil.example.com/*' => Http::response('should never be fetched', 200),
        ]);

        try {
            $this->client()->openRecordingStream(self::DOWNLOAD_URL);
            $this->fail('Expected the redirect to be refused.');
        } catch (GatewayRequestException $e) {
            $this->assertStringContainsString('non-Zoom host [evil.example.com]', $e->getMessage());
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
            $this->assertStringNotContainsString('collect?next', $e->getMessage(), 'the redirect URL is never echoed');
        }

        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'evil.example.com'));
    }

    /** A lookalike host that merely CONTAINS "zoom.us" is not Zoom. */
    public function test_a_lookalike_redirect_host_is_refused(): void
    {
        $this->fakeDownload([
            'zoom.us/rec/download/*' => Http::response('', 302, ['Location' => 'https://zoom.us.attacker.example/rec/x']),
            'zoom.us.attacker.example/*' => Http::response('nope', 200),
        ]);

        $this->expectException(GatewayRequestException::class);
        $this->expectExceptionMessage('non-Zoom host [zoom.us.attacker.example]');

        try {
            $this->client()->openRecordingStream(self::DOWNLOAD_URL);
        } finally {
            Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'attacker.example'));
        }
    }

    /** A downgrade to plain HTTP — even on a Zoom host — is refused: the bearer token would travel in clear text. */
    public function test_a_redirect_to_a_plain_http_destination_is_refused(): void
    {
        $this->fakeDownload([
            'zoom.us/rec/download/*' => Http::response('', 302, ['Location' => 'http://us06web.zoom.us/rec/cdn/abc123']),
            'us06web.zoom.us/*' => Http::response('nope', 200),
        ]);

        $this->expectException(GatewayRequestException::class);
        $this->expectExceptionMessage('non-Zoom host');

        try {
            $this->client()->openRecordingStream(self::DOWNLOAD_URL);
        } finally {
            Http::assertNotSent(fn (Request $r): bool => str_starts_with($r->url(), 'http://'));
        }
    }

    public function test_the_initial_download_url_itself_must_be_https_on_an_approved_host(): void
    {
        $this->fakeDownload([]);

        $this->expectException(GatewayRequestException::class);
        $this->expectExceptionMessage('non-Zoom host');

        $this->client()->openRecordingStream('http://zoom.us/rec/download/abc123');
    }

    /** A relative Location header resolves against the hop that issued it, and is then validated like any other. */
    public function test_a_relative_redirect_is_resolved_against_the_issuing_host(): void
    {
        $bytes = $this->mp4Bytes();
        $this->fakeDownload([
            'zoom.us/rec/download/*' => Http::response('', 302, ['Location' => '/rec/cdn/abc123?sig=1']),
            'zoom.us/rec/cdn/*' => Http::response($bytes, 200),
        ]);

        $download = $this->client()->openRecordingStream(self::DOWNLOAD_URL);

        $this->assertSame($bytes, stream_get_contents($download->stream));
        $this->assertNull($download->expectedBytes, 'no Content-Length means no declared length');
        $download->close();
        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://zoom.us/rec/cdn/abc123?sig=1');
    }

    public function test_redirects_are_bounded(): void
    {
        config(['recordings.zoom.download_max_redirects' => 2]);
        $this->fakeDownload([
            // Every hop redirects to itself: an endless loop.
            'zoom.us/rec/download/*' => Http::response('', 302, ['Location' => self::DOWNLOAD_URL]),
        ]);

        try {
            $this->client()->openRecordingStream(self::DOWNLOAD_URL);
            $this->fail('Expected the redirect loop to be cut off.');
        } catch (GatewayRequestException $e) {
            $this->assertStringContainsString('more than 2 redirects', $e->getMessage());
        }

        // The initial request plus exactly max_redirects follow-ups, then stop.
        Http::assertSentCount(1 + 3);
    }

    public function test_a_redirect_without_a_destination_is_refused(): void
    {
        $this->fakeDownload([
            'zoom.us/rec/download/*' => Http::response('', 302),
        ]);

        $this->expectException(GatewayRequestException::class);
        $this->expectExceptionMessage('redirect without a destination');

        $this->client()->openRecordingStream(self::DOWNLOAD_URL);
    }

    /**
     * The ceiling is applied to the DECLARED length before a single body
     * byte is read — the staging disk must never fill up first. (The
     * pump re-applies it to the bytes that actually arrive.)
     */
    public function test_a_declared_length_above_the_ceiling_is_refused_before_the_body_is_read(): void
    {
        config(['recordings.max_source_bytes' => 1024]);
        $this->fakeDownload([
            'zoom.us/rec/download/*' => Http::response(str_repeat('x', 2048), 200, ['Content-Length' => '2048']),
        ]);

        $this->expectException(GatewayRequestException::class);
        $this->expectExceptionMessage('exceeds the configured size ceiling');

        $this->client()->openRecordingStream(self::DOWNLOAD_URL);
    }

    public function test_an_error_status_on_the_download_is_reported_without_the_url(): void
    {
        $this->fakeDownload([
            'zoom.us/rec/download/*' => Http::response('', 401),
        ]);

        try {
            $this->client()->openRecordingStream(self::DOWNLOAD_URL);
            $this->fail('Expected a GatewayRequestException.');
        } catch (GatewayRequestException $e) {
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
            $this->assertStringNotContainsString('abc123', $e->getMessage());
        }
    }

    public function test_the_download_request_carries_bounded_timeouts_streams_and_does_not_auto_follow_redirects(): void
    {
        config([
            'recordings.zoom.download_timeout' => 123,
            'recordings.zoom.download_connect_timeout' => 7,
        ]);

        $options = null;
        Http::fake(function (Request $request, array $requestOptions) use (&$options) {
            if (str_contains($request->url(), 'oauth/token')) {
                return Http::response(['access_token' => self::TOKEN, 'expires_in' => 3600]);
            }

            $options = $requestOptions;

            return Http::response($this->mp4Bytes(), 200);
        });

        $this->client()->openRecordingStream(self::DOWNLOAD_URL)->close();

        $this->assertNotNull($options);
        $this->assertSame(123, (int) $options['timeout']);
        $this->assertSame(7, (int) $options['connect_timeout']);
        $this->assertSame(123, (int) $options['read_timeout']);
        $this->assertFalse($options['allow_redirects'], 'redirects must be followed by hand, never by the client');
        $this->assertTrue($options['stream'], 'the body must be streamed, never buffered');
    }
}
