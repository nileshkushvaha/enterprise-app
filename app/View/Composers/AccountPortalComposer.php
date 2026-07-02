<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\DTOs\AccountProfileSummaryData;
use App\Services\Account\AccountMenuService;
use App\Services\Profile\ProfileService;
use Illuminate\View\View;

/**
 * Supplies the shared view data every Account Portal page needs, so
 * controllers stop repeating auth()->user() lookups and menu/profile
 * plumbing. Bound only to layouts.account (see AppServiceProvider::boot()).
 */
final class AccountPortalComposer
{
    public function __construct(
        private readonly AccountMenuService $menuService,
        private readonly ProfileService $profileService,
    ) {}

    public function compose(View $view): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $user->loadMissing('profile.media');

        $view->with([
            'accountMenu' => $this->menuService->items($user),
            'accountProfileSummary' => AccountProfileSummaryData::fromUser(
                $user,
                $this->profileService->completion($user),
            ),
            'accountNotificationCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
