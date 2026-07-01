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

    // Gate::before() handles the super_admin bypass automatically,
    // so this single can() check covers both super_admin and manager.
    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('View:StatsOverviewWidget');
    }

    protected function getStats(): array
    {
        // Single aggregated query replaces four separate count() calls
        $userStats = User::selectRaw(
            'COUNT(*) as total,
             SUM(status = ?) as active,
             SUM(status IN (?,?)) as blocked,
             SUM(MONTH(created_at) = ? AND YEAR(created_at) = ?) as new_this_month',
            [User::STATUS_ACTIVE, User::STATUS_BLOCKED, User::STATUS_SUSPENDED, now()->month, now()->year]
        )->first();

        // One date-range GROUP BY query replaces 7 individual whereDate() calls
        $dailyCounts = User::selectRaw('DATE(created_at) as day, COUNT(*) as cnt')
            ->where('created_at', '>=', today()->subDays(6)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('cnt', 'day');

        $chart = collect(range(6, 0))
            ->map(fn (int $i) => (int) ($dailyCounts[today()->subDays($i)->format('Y-m-d')] ?? 0))
            ->values()
            ->all();

        $totalRoles = Role::count();
        $totalPermissions = Permission::count();
        $todayLogins = LoginHistory::whereDate('logged_in_at', today())->count();

        return [
            Stat::make('Total Users', (int) ($userStats->total ?? 0))
                ->description('+'.((int) ($userStats->new_this_month ?? 0)).' this month')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->icon('heroicon-o-users')
                ->chart($chart),

            Stat::make('Active Users', (int) ($userStats->active ?? 0))
                ->description(((int) ($userStats->blocked ?? 0)).' blocked / suspended')
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
