<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

/**
 * The provider request did not complete — a transport failure or
 * timeout after the request left this host — so a remote meeting MAY
 * exist without SIRI holding its id. Distinct from an ordinary failure
 * (an HTTP error status, where the provider definitely created
 * nothing) because the two must be handled differently: a definite
 * failure may be retried, an ambiguous one must be RECONCILED first or
 * a retry creates a duplicate remote meeting.
 */
final class AmbiguousMeetingCreationException extends BookingException {}
