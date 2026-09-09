<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OperationalHealthService;
use Illuminate\Console\Command;

/**
 * The signal an external monitor watches to notice that something has
 * quietly STOPPED.
 *
 * The platform already records failures well. What it had no answer for
 * was absence: a scheduler that is not running produces no failed row,
 * no exception and no log line, so silence looked exactly like health —
 * and recurring class generation depends entirely on that scheduler.
 *
 * Exit codes are the contract, so this works with any monitor that can
 * run a command (Supervisor eventlistener, Nagios/Icinga, a cron+curl
 * uptime probe, a systemd OnFailure unit):
 *
 *     0  ok        everything expected to be running, is
 *     1  warning   degraded — failures recorded, money owed, worth looking at
 *     2  critical  something has stopped, or has never run at all
 *
 * "Never run" is deliberately CRITICAL rather than unknown. It is
 * exactly the state a missing or misconfigured scheduler produces, and
 * grading it as merely unknown would hide the single failure this
 * command exists to catch.
 */
class CheckPlatformHealth extends Command
{
    protected $signature = 'platform:health-check {--json : Emit the full report as JSON}';

    protected $description = 'Report scheduler, queue and recurring-generation health for external monitoring';

    public function handle(OperationalHealthService $health): int
    {
        $report = $health->report();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf('Overall: %s', strtoupper($report['status'])));

            $this->table(
                ['Check', 'Status', 'Summary'],
                array_map(
                    static fn (array $check): array => [$check['name'], $check['status'], $check['summary']],
                    $report['checks'],
                ),
            );
        }

        return match ($report['status']) {
            OperationalHealthService::OK => self::SUCCESS,
            OperationalHealthService::WARNING => 1,
            default => 2,
        };
    }
}
