<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

/**
 * An open, authenticated read stream for one provider recording,
 * together with what the provider's response DECLARED about it.
 *
 * `expectedBytes` is the response's Content-Length when one was sent.
 * It is a hint the staging pump uses to detect a connection that
 * closed early — a socket that drops mid-body simply reports EOF, and
 * without the declared length a truncated class video would be staged
 * as if it were complete. It is never trusted as a safety limit: the
 * size ceiling is enforced on the bytes that actually arrive.
 *
 * The stream is a plain resource so nothing above the gateway holds
 * an HTTP client or a response object; the caller owns closing it.
 */
final class ProviderDownloadStream
{
    /**
     * @param  resource  $stream
     */
    public function __construct(
        public readonly mixed $stream,
        public readonly ?int $expectedBytes = null,
    ) {}

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
