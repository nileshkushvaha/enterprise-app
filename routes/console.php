<?php

use App\Console\Commands\PublishScheduledContent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-publish scheduled Pages and Posts every minute.
// The command is idempotent: it only touches records whose published_at <= now().
app(Schedule::class)
    ->command(PublishScheduledContent::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduled-publishing.log'));
