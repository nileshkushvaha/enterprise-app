<?php

declare(strict_types=1);

namespace App\Navigation\Services;

use App\Enums\Navigation\NavigationLocation;
use App\Enums\Navigation\NavigationStatus;
use App\Models\NavigationMenu;
use App\Navigation\Contracts\NavigationRepositoryInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NavigationRepository implements NavigationRepositoryInterface
{
    private const ITEM_RELATIONS = ['linkable', 'roles', 'permissions'];

    public function findByLocation(NavigationLocation $location, ?string $locale = null): ?NavigationMenu
    {
        return NavigationMenu::query()
            ->where('location', $location->value)
            ->where('status', NavigationStatus::Published->value)
            ->when(
                $locale !== null,
                fn ($q) => $q->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale')),
                fn ($q) => $q->whereNull('locale'),
            )
            ->with(['items' => $this->itemsQuery(...)])
            ->first();
    }

    public function findBySlug(string $slug): ?NavigationMenu
    {
        return NavigationMenu::query()
            ->where('slug', $slug)
            ->where('status', NavigationStatus::Published->value)
            ->with(['items' => $this->itemsQuery(...)])
            ->first();
    }

    private function itemsQuery(HasMany $query): void
    {
        $query
            ->active()
            ->orderBy('_lft')
            ->with(self::ITEM_RELATIONS);
    }
}
