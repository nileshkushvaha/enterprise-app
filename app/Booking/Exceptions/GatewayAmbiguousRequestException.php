<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

/**
 * A gateway request whose outcome is UNKNOWN: the connection failed or
 * timed out after the request may have been sent, so the remote side
 * may have acted on it. Callers that create remote resources must not
 * treat this like a definite rejection.
 */
final class GatewayAmbiguousRequestException extends GatewayRequestException {}
