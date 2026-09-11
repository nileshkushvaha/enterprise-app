<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Models\Booking;
use App\Models\User;
use App\Settings\MeetingSettings;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Cross-host authorization for the participant join gateway.
 *
 * The session cookie is host-only (SESSION_DOMAIN is unset), so a
 * participant signed in on the main site is a guest on the meeting
 * host. Rather than widening the cookie or signing the user into a full
 * web session there, the main host — where the session exists — issues
 * a handoff token, and the meeting host exchanges it for a JOIN GRANT:
 * a booking-scoped authorization kept in the meeting host's own session
 * that lets exactly one user pass the gateway for exactly one booking,
 * for a few minutes. A grant is not a login: dashboard and account
 * routes still see a guest.
 *
 * Token properties: random, server-side, bound to user + booking + the
 * configured HTTPS meeting origin, valid for TOKEN_TTL_SECONDS, and
 * redeemed at most once across every web node — the redemption marker
 * is written with Cache::add(), an atomic insert on the shared store
 * (database/Redis), so two nodes racing on the same token cannot both
 * win. get()+forget() would not give that guarantee.
 *
 * This class also owns the origins and URLs the flow moves between, so
 * the middleware and controllers never assemble them by hand.
 */
final class MeetingJoinHandoffService
{
    public const int TOKEN_TTL_SECONDS = 60;

    /** How long a redeemed grant lets its user through the gateway on the meeting host. */
    public const int GRANT_TTL_SECONDS = 600;

    public const string QUERY_PARAMETER = 'handoff';

    private const string TOKEN_PREFIX = 'meeting-join-handoff:token:';

    private const string REDEEMED_PREFIX = 'meeting-join-handoff:redeemed:';

    private const string SESSION_KEY = 'meeting_join_grants';

    private const int TOKEN_LENGTH = 48;

    public function __construct(
        private readonly BookingMeetingServiceInterface $meetings,
        private readonly MeetingSettings $settings,
    ) {}

    // ── Origins ───────────────────────────────────────────────────────

    /**
     * The configured meeting origin (`https://meet.example.com`), or null
     * when unset or invalid. Anything but a bare HTTPS origin — a path,
     * query, fragment, or credentials — is rejected rather than trimmed.
     */
    public function meetingOrigin(): ?string
    {
        return self::normalizeOrigin($this->settings->participant_join_base_url);
    }

    /** Validates and canonicalises a candidate Join Link Domain; null when it is not a bare HTTPS origin. */
    public static function normalizeOrigin(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || blank($parts['host'] ?? null)) {
            return null;
        }

        foreach (['user', 'pass', 'query', 'fragment'] as $forbidden) {
            if (isset($parts[$forbidden])) {
                return null;
            }
        }

        if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
            return null;
        }

        $origin = 'https://'.strtolower((string) $parts['host']);

        return isset($parts['port']) ? $origin.':'.$parts['port'] : $origin;
    }

    public function mainOrigin(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /** Whether the request arrived on APP_URL's host — where sessions live and `auth` would redirect to login. */
    public function isMainHost(Request $request): bool
    {
        $main = parse_url($this->mainOrigin(), PHP_URL_HOST);

        return ! is_string($main) || strcasecmp($main, $request->getHost()) === 0;
    }

    /** Whether the request arrived over HTTPS on exactly the configured meeting origin. */
    public function isMeetingOrigin(Request $request): bool
    {
        $origin = $this->meetingOrigin();

        return $origin !== null && strcasecmp($request->getSchemeAndHttpHost(), $origin) === 0;
    }

    // ── Tokens ────────────────────────────────────────────────────────

    /**
     * Mints a token the participant carries to the meeting host. Only
     * meaningful when a meeting origin is configured; the token is bound
     * to that origin and cannot be redeemed anywhere else.
     */
    public function issue(User $user, Booking $booking): ?string
    {
        $origin = $this->meetingOrigin();

        if ($origin === null) {
            return null;
        }

        $token = Str::random(self::TOKEN_LENGTH);

        Cache::put(
            self::TOKEN_PREFIX.$token,
            ['user_id' => $user->id, 'booking_id' => (string) $booking->getKey(), 'origin' => $origin],
            now()->addSeconds(self::TOKEN_TTL_SECONDS),
        );

        return $token;
    }

    /**
     * Redeems a token for the given booking on the given request.
     * Returns the user it was issued to, or null. Exactly-once: the
     * first caller to write the redemption marker (an atomic add on the
     * shared cache) wins; every other caller, on any node, gets null.
     * The token is consumed even when it does not match the booking or
     * the origin, so it can never be tried again elsewhere.
     */
    public function redeem(string $token, Booking $booking, Request $request): ?User
    {
        if (strlen($token) !== self::TOKEN_LENGTH || ! ctype_alnum($token)) {
            return null;
        }

        if (! Cache::add(self::REDEEMED_PREFIX.$token, true, now()->addSeconds(self::TOKEN_TTL_SECONDS * 2))) {
            return null; // already redeemed, possibly by another node a moment ago
        }

        $payload = Cache::pull(self::TOKEN_PREFIX.$token);

        if (! is_array($payload)) {
            return null;
        }

        $matches = ($payload['booking_id'] ?? null) === (string) $booking->getKey()
            && is_string($payload['origin'] ?? null)
            && strcasecmp($payload['origin'], $request->getSchemeAndHttpHost()) === 0
            && $this->isMeetingOrigin($request);

        if (! $matches) {
            return null;
        }

        return User::query()->find($payload['user_id'] ?? null);
    }

    // ── Grants (meeting-host session, booking-scoped) ─────────────────

    /** Records that $user may pass the gateway for $booking on this host, for GRANT_TTL_SECONDS. */
    public function grant(Session $session, User $user, Booking $booking): void
    {
        $grants = (array) $session->get(self::SESSION_KEY, []);
        $grants[(string) $booking->getKey()] = ['user_id' => $user->id, 'expires_at' => now()->addSeconds(self::GRANT_TTL_SECONDS)->getTimestamp()];

        $session->put(self::SESSION_KEY, $grants);
    }

    /** The user a live grant in this session names for $booking, or null. Nothing else in the application reads grants. */
    public function grantedUser(Session $session, Booking $booking): ?User
    {
        $grant = ((array) $session->get(self::SESSION_KEY, []))[(string) $booking->getKey()] ?? null;

        if (! is_array($grant) || (int) ($grant['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return User::query()->find($grant['user_id'] ?? null);
    }

    // ── URLs ──────────────────────────────────────────────────────────

    /** Where the meeting host sends a guest: the main host's handoff endpoint, on APP_URL. */
    public function mainHostHandoffUrl(Booking $booking): string
    {
        return $this->mainOrigin().route('dashboard.meetings.handoff', $booking, absolute: false);
    }

    /** Where the main host sends a participant back: the join link carrying the token. */
    public function joinUrlWithToken(Booking $booking, string $token): string
    {
        return $this->meetings->joinLinkFor($booking).'?'.http_build_query([self::QUERY_PARAMETER => $token]);
    }
}
