<?php

declare(strict_types=1);

namespace App\Services\Payment;

final class GatewayConnectionResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $message,
        /** @var array<string, mixed> */
        public readonly array $meta = [],
    ) {}
}
