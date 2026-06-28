<?php

declare(strict_types=1);

namespace App\Navigation\Registry;

use App\Enums\Navigation\NavigationLinkType;
use App\Navigation\Contracts\LinkTypeDriverInterface;
use InvalidArgumentException;

final class LinkTypeRegistry
{
    /** @var array<string, LinkTypeDriverInterface> */
    private array $drivers = [];

    public function register(LinkTypeDriverInterface $driver): void
    {
        foreach (NavigationLinkType::cases() as $type) {
            if ($driver->supports($type)) {
                $this->drivers[$type->value] = $driver;
            }
        }
    }

    public function registerFor(NavigationLinkType $type, LinkTypeDriverInterface $driver): void
    {
        $this->drivers[$type->value] = $driver;
    }

    public function resolve(NavigationLinkType $type): LinkTypeDriverInterface
    {
        if (! isset($this->drivers[$type->value])) {
            throw new InvalidArgumentException(
                "No link type driver registered for [{$type->value}]."
            );
        }

        return $this->drivers[$type->value];
    }

    public function has(NavigationLinkType $type): bool
    {
        return isset($this->drivers[$type->value]);
    }

    /** @return string[] */
    public function registeredTypes(): array
    {
        return array_keys($this->drivers);
    }
}
