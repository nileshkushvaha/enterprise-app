<?php

declare(strict_types=1);

namespace App\Navigation\DTOs;

readonly class ResolvedLink
{
    /** @param array<string, string> $attributes */
    public function __construct(
        public string $url,
        public string $target,
        public ?string $rel,
        public array $attributes,
    ) {}

    public function isExternal(): bool
    {
        return $this->target === '_blank';
    }

    public function isEmpty(): bool
    {
        return $this->url === '' || $this->url === '#';
    }
}
