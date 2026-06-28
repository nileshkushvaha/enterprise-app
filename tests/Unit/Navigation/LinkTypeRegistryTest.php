<?php

declare(strict_types=1);

namespace Tests\Unit\Navigation;

use App\Enums\Navigation\NavigationLinkType;
use App\Models\NavigationItem;
use App\Navigation\Contracts\LinkTypeDriverInterface;
use App\Navigation\DTOs\ResolvedLink;
use App\Navigation\Registry\LinkTypeRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class LinkTypeRegistryTest extends TestCase
{
    private function makeDriver(NavigationLinkType ...$types): LinkTypeDriverInterface
    {
        return new class($types) implements LinkTypeDriverInterface {
            public function __construct(private readonly array $types) {}

            public function resolve(NavigationItem $item): ResolvedLink
            {
                return new ResolvedLink('#', '_self', null, []);
            }

            public function supports(NavigationLinkType $type): bool
            {
                return in_array($type, $this->types, true);
            }
        };
    }

    public function test_register_maps_driver_to_its_supported_types(): void
    {
        $registry = new LinkTypeRegistry();
        $driver   = $this->makeDriver(NavigationLinkType::Url, NavigationLinkType::External);

        $registry->register($driver);

        $this->assertTrue($registry->has(NavigationLinkType::Url));
        $this->assertTrue($registry->has(NavigationLinkType::External));
        $this->assertFalse($registry->has(NavigationLinkType::Page));
    }

    public function test_register_for_overwrites_specific_type(): void
    {
        $registry = new LinkTypeRegistry();
        $first    = $this->makeDriver(NavigationLinkType::Url);
        $second   = $this->makeDriver(NavigationLinkType::Url);

        $registry->register($first);
        $registry->registerFor(NavigationLinkType::Url, $second);

        $this->assertSame($second, $registry->resolve(NavigationLinkType::Url));
    }

    public function test_resolve_returns_correct_driver(): void
    {
        $registry = new LinkTypeRegistry();
        $driver   = $this->makeDriver(NavigationLinkType::Email);
        $registry->register($driver);

        $resolved = $registry->resolve(NavigationLinkType::Email);

        $this->assertSame($driver, $resolved);
    }

    public function test_resolve_throws_for_unregistered_type(): void
    {
        $registry = new LinkTypeRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/page/');

        $registry->resolve(NavigationLinkType::Page);
    }

    public function test_registered_types_returns_all_registered_values(): void
    {
        $registry = new LinkTypeRegistry();
        $registry->register($this->makeDriver(NavigationLinkType::Url, NavigationLinkType::External));

        $types = $registry->registeredTypes();

        $this->assertContains('url', $types);
        $this->assertContains('external', $types);
        $this->assertNotContains('page', $types);
    }

    public function test_has_returns_false_for_empty_registry(): void
    {
        $registry = new LinkTypeRegistry();

        $this->assertFalse($registry->has(NavigationLinkType::Page));
    }
}
