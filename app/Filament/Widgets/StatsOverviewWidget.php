<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\LoginHistory;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', User::STATUS_ACTIVE)->count();
        $blockedUsers = User::whereIn('status', [User::STATUS_BLOCKED, User::STATUS_SUSPENDED])->count();
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalRoles = Role::count();
        $totalPermissions = Permission::count();

        $todayLogins = LoginHistory::whereDate('logged_in_at', today())->count();

        return [
            Stat::make('Total Users', $totalUsers)
                ->description("+{$newUsersThisMonth} this month")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->icon('heroicon-o-users')
                ->chart([
                    User::whereDate('created_at', today()->subDays(6))->count(),
                    User::whereDate('created_at', today()->subDays(5))->count(),
                    User::whereDate('created_at', today()->subDays(4))->count(),
                    User::whereDate('created_at', today()->subDays(3))->count(),
                    User::whereDate('created_at', today()->subDays(2))->count(),
                    User::whereDate('created_at', today()->subDays(1))->count(),
                    User::whereDate('created_at', today())->count(),
                ]),

            Stat::make('Active Users', $activeUsers)
                ->description("{$blockedUsers} blocked / suspended")
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('info')
                ->icon('heroicon-o-user-circle'),

            Stat::make('Roles', $totalRoles)
                ->description("{$totalPermissions} permissions configured")
                ->descriptionIcon('heroicon-m-key')
                ->color('warning')
                ->icon('heroicon-o-shield-check'),

            Stat::make("Today's Logins", $todayLogins)
                ->description('Login activity today')
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary')
                ->icon('heroicon-o-finger-print'),
        ];
    }
}
