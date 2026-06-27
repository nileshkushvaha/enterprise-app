<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\RecentLoginsWidget;
use App\Filament\Widgets\RecentUsersWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title = 'Dashboard';
    protected static ?int $navigationSort = -2;

    public function getHeading(): string|Htmlable
    {
        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };

        $name = auth()->user()?->first_name ?? auth()->user()?->name ?? 'Admin';

        return "{$greeting}, {$name} 👋";
    }

    public function getSubheading(): string|Htmlable|null
    {
        return "Here's what's happening with your platform today — " . now()->format('l, F j, Y');
    }

    public function getBreadcrumbs(): array
    {
        return [
            '#' => 'Dashboard',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_site')
                ->label('View Site')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url('/')
                ->openUrlInNewTab()
                ->color('gray'),
        ];
    }

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            RecentUsersWidget::class,
            RecentLoginsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
