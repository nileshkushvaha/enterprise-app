<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Cross-host sign-in for the participant join gateway.
 *
 * The session cookie is host-only (SESSION_DOMAIN is unset), so a
 * participant signed in on the main site is a guest on
 * meet.sirieducation.com. Rather than widening the cookie to the whole
 * domain, the main host — where the session already exists — issues a
 * handoff token: random, bound to one user and one booking, stored
 * server-side, valid for TTL_SECONDS, consumed exactly once. The join
 * host redeems it, opens its own session for that user, and drops the
 * token from the URL before the gateway runs its usual checks. The
 * token is not a credential for anything else: it can only start a
 * session that is then subject to every gateway rule.
 *
 * This class also owns the two URLs the flow bounces between, so the
 * middleware and the controller never assemble them by hand.
 */
final class MeetingJoinHandoffService
{
    public const int TTL_SECONDS = 60;

    public const string QUERY_PARAMETER = 'handoff';

    private const string CACHE_PREFIX = 'meeting-join-handoff:';

    private const int TOKEN_LENGTH = 48;

    public function __construct(
        private readonly BookingMeetingServiceInterface $meetings,
    ) {}

    /** Mints a token the participant carries to the join host. */
    public function issue(User $user, Booking $booking): string
    {
        $token = Str::random(self::TOKEN_LENGTH);

        Cache::put(
            self::CACHE_PREFIX.$token,
            ['user_id' => $user->id, 'booking_id' => (string) $booking->getKey()],
            now()->addSeconds(self::TTL_SECONDS),
        );

        return $token;
    }

    /**
     * Redeems a token for the given booking. Single-use: the entry is
     * removed whether or not it matches, so a token can never be tried
     * twice. Returns the user to sign in, or null.
     */
    public function redeem(string $token, Booking $booking): ?User
    {
        if (strlen($token) !== self::TOKEN_LENGTH) {
            return null;
        }

        $payload = Cache::pull(self::CACHE_PREFIX.$token);

        if (! is_array($payload) || ($payload['booking_id'] ?? null) !== (string) $booking->getKey()) {
            return null;
        }

        return User::query()->find($payload['user_id'] ?? null);
    }

    /** Where the join host sends a guest: the main host's handoff endpoint, on APP_URL. */
    public function mainHostHandoffUrl(Booking $booking): string
    {
        return rtrim((string) config('app.url'), '/').route('dashboard.meetings.handoff', $booking, absolute: false);
    }

    /** Where the main host sends a participant back: the join link carrying the token. */
    public function joinUrlWithToken(Booking $booking, string $token): string
    {
        return $this->meetings->joinLinkFor($booking).'?'.http_build_query([self::QUERY_PARAMETER => $token]);
    }

    /** Whether the request arrived on APP_URL's host — where the session lives and `auth` would redirect to login. */
    public function isMainHost(Request $request): bool
    {
        $main = parse_url((string) config('app.url'), PHP_URL_HOST);

        return ! is_string($main) || strcasecmp($main, $request->getHost()) === 0;
    }
}
