<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Spatie\Activitylog\Facades\Activity;

class CacheManagerService
{
    // ── System info ──────────────────────────────────────────────────────

    public function getCacheDriver(): string
    {
        return config('cache.default', 'unknown');
    }

    public function getCacheStore(): string
    {
        $driver = $this->getCacheDriver();
        $store  = config("cache.stores.{$driver}", []);

        return match ($driver) {
            'redis'      => 'Redis — ' . ($store['connection'] ?? 'default'),
            'memcached'  => 'Memcached',
            'database'   => 'Database — ' . ($store['table'] ?? 'cache'),
            'file'       => 'File — ' . ($store['path'] ?? storage_path('framework/cache')),
            'array'      => 'Array (in-memory)',
            'null'       => 'Null (disabled)',
            default      => ucfirst($driver),
        };
    }

    public function isConfigCached(): bool
    {
        return file_exists(base_path('bootstrap/cache/config.php'));
    }

    public function isRouteCached(): bool
    {
        return file_exists(base_path('bootstrap/cache/routes-v7.php'))
            || file_exists(base_path('bootstrap/cache/routes.php'));
    }

    public function isViewCached(): bool
    {
        $viewPath = storage_path('framework/views');

        if (! is_dir($viewPath)) {
            return false;
        }

        return count(glob("{$viewPath}/*.php") ?: []) > 0;
    }

    public function isEventCached(): bool
    {
        return file_exists(base_path('bootstrap/cache/events.php'));
    }

    public function getEnvironment(): string
    {
        return app()->environment();
    }

    public function getLaravelVersion(): string
    {
        return app()->version();
    }

    public function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    // ── Cache actions ─────────────────────────────────────────────────────

    public function clearApplicationCache(): array
    {
        $exitCode = Artisan::call('cache:clear');
        $output   = trim(Artisan::output());

        $this->log('cache:clear', $output, $exitCode);

        return $this->result($exitCode, $output, 'Application cache cleared successfully.');
    }

    public function clearViewCache(): array
    {
        $exitCode = Artisan::call('view:clear');
        $output   = trim(Artisan::output());

        $this->log('view:clear', $output, $exitCode);

        return $this->result($exitCode, $output, 'View cache cleared successfully.');
    }

    public function clearRouteCache(): array
    {
        $exitCode = Artisan::call('route:clear');
        $output   = trim(Artisan::output());

        $this->log('route:clear', $output, $exitCode);

        return $this->result($exitCode, $output, 'Route cache cleared successfully.');
    }

    public function clearConfigCache(): array
    {
        $exitCode = Artisan::call('config:clear');
        $output   = trim(Artisan::output());

        $this->log('config:clear', $output, $exitCode);

        return $this->result($exitCode, $output, 'Config cache cleared successfully.');
    }

    public function clearEventCache(): array
    {
        $exitCode = Artisan::call('event:clear');
        $output   = trim(Artisan::output());

        $this->log('event:clear', $output, $exitCode);

        return $this->result($exitCode, $output, 'Event cache cleared successfully.');
    }

    public function optimize(): array
    {
        $exitCode = Artisan::call('optimize');
        $output   = trim(Artisan::output());

        $this->log('optimize', $output, $exitCode);

        return $this->result($exitCode, $output, 'Application optimized successfully.');
    }

    public function optimizeClear(): array
    {
        $exitCode = Artisan::call('optimize:clear');
        $output   = trim(Artisan::output());

        $this->log('optimize:clear', $output, $exitCode);

        return $this->result($exitCode, $output, 'All caches cleared successfully.');
    }

    // ── Internals ─────────────────────────────────────────────────────────

    private function result(int $exitCode, string $output, string $successMessage): array
    {
        $success = $exitCode === 0;

        return [
            'success'   => $success,
            'message'   => $success ? $successMessage : 'Command failed.',
            'output'    => $output ?: ($success ? $successMessage : 'Command returned a non-zero exit code.'),
            'exitCode'  => $exitCode,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    private function log(string $command, string $output, int $exitCode): void
    {
        $logger = activity('cache_manager')
            ->withProperties([
                'command'   => $command,
                'exit_code' => $exitCode,
                'output'    => $output,
            ]);

        if ($user = auth()->user()) {
            $logger = $logger->causedBy($user);
        }

        $logger->log("Executed artisan {$command}");
    }
}
