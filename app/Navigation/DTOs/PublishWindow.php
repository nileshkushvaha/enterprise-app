<?php

declare(strict_types=1);

namespace App\Navigation\DTOs;

use Carbon\CarbonInterface;

readonly class PublishWindow
{
    public function __construct(
        public ?CarbonInterface $startsAt,
        public ?CarbonInterface $endsAt,
    ) {}

    public static function always(): self
    {
        return new self(null, null);
    }

    public static function from(?CarbonInterface $startsAt, ?CarbonInterface $endsAt): self
    {
        return new self($startsAt, $endsAt);
    }

    public function isActive(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        if ($this->startsAt !== null && $now->isBefore($this->startsAt)) {
            return false;
        }

        if ($this->endsAt !== null && $now->isAfter($this->endsAt)) {
            return false;
        }

        return true;
    }

    public function isConstrained(): bool
    {
        return $this->startsAt !== null || $this->endsAt !== null;
    }
}
