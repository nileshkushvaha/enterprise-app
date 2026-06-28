<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

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
        return $this->run('cache:clear', 'Application cache cleared successfully.');
    }

    public function clearViewCache(): array
    {
        return $this->run('view:clear', 'View cache cleared successfully.');
    }

    public function clearRouteCache(): array
    {
        return $this->run('route:clear', 'Route cache cleared successfully.');
    }

    public function clearConfigCache(): array
    {
        return $this->run('config:clear', 'Config cache cleared successfully.');
    }

    public function clearEventCache(): array
    {
        return $this->run('event:clear', 'Event cache cleared successfully.');
    }

    public function optimize(): array
    {
        return $this->run('optimize', 'Application optimized successfully.');
    }

    public function optimizeClear(): array
    {
        return $this->run('optimize:clear', 'All caches cleared successfully.');
    }

    // ── Internals ─────────────────────────────────────────────────────────

    private function run(string $command, string $successMessage): array
    {
        $buffer   = new BufferedOutput();
        $exitCode = Artisan::call($command, [], $buffer);
        $output   = trim($buffer->fetch());

        $this->logActivity($command, $output, $exitCode);

        $success = $exitCode === 0;

        return [
            'success'   => $success,
            'message'   => $success ? $successMessage : 'Command failed.',
            'output'    => $output ?: ($success ? $successMessage : 'Command returned a non-zero exit code.'),
            'exitCode'  => $exitCode,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    private function logActivity(string $command, string $output, int $exitCode): void
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
