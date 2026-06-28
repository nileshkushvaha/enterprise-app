<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DoctorCommand extends Command
{
    protected $signature = 'app:doctor';

    protected $description = 'Verify the current environment configuration';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=blue;options=bold>Environment Check</>');
        $this->newLine();

        $checks = $this->buildChecks();
        $failed = 0;

        foreach ($checks as $label => [$value, $ok, $hint]) {
            if (! $ok) {
                $failed++;
            }

            $icon = $ok ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $val = $ok ? "<fg=green>{$value}</>" : "<fg=red>{$value}</>";
            $pad = str_pad($label, 20);

            $this->line("  {$icon}  {$pad} {$val}");

            if (! $ok && $hint) {
                $this->line("     <fg=yellow>→ {$hint}</>");
            }
        }

        $this->newLine();

        if ($failed > 0) {
            $this->line("  <fg=red>{$failed} check(s) failed.</>");
        } else {
            $this->line('  <fg=green>All checks passed.</>');
        }

        $this->newLine();

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function buildChecks(): array
    {
        $env = app()->environment();
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        $dbOk = $this->canConnectToDatabase();

        $envOk = in_array($env, ['local', 'testing'], true);
        $envHint = $envOk ? null : "Unexpected environment [{$env}] — expected local or testing";

        return [
            'Environment' => [$env,        $envOk, $envHint],
            'Connection' => [$connection,  true,   null],
            'Database' => [$database,    $dbOk,  $dbOk ? null : "Cannot connect to [{$database}] — check credentials"],
            'Cache' => [config('cache.default'),   true, null],
            'Queue' => [config('queue.default'),   true, null],
            'Mail' => [config('mail.default'),    true, null],
            'Session' => [config('session.driver'),  true, null],
        ];
    }

    private function canConnectToDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
