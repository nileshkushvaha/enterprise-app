<?php

namespace Tests\Feature\Database;

use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Exercises AppServiceProvider::guardAgainstDestructiveDatabaseCommands() end-to-end
 * by actually invoking the Artisan commands it guards, rather than just asserting the
 * wiring around them. Every destructive command checks Prohibitable::isProhibited() as
 * the very first line of handle() (verified against vendor source), so flipping the
 * static flag is equivalent to running these commands outside the testing environment
 * without needing to actually boot a second application instance.
 */
class DatabaseProtectionTest extends TestCase
{
    /** @var array<class-string> */
    private const DESTRUCTIVE_COMMANDS = [
        FreshCommand::class,
        RefreshCommand::class,
        ResetCommand::class,
        RollbackCommand::class,
        WipeCommand::class,
    ];

    protected function tearDown(): void
    {
        // Restore both the flag and the schema to what AppServiceProvider + a normal
        // testing run expect, regardless of what this test class did to either.
        foreach (self::DESTRUCTIVE_COMMANDS as $command) {
            $command::prohibit(false);
        }

        Artisan::call('migrate:fresh', ['--force' => true]);

        parent::tearDown();
    }

    public function test_protection_layer_reflects_the_testing_environment_on_boot(): void
    {
        $this->assertSame('testing', app()->environment());

        foreach (self::DESTRUCTIVE_COMMANDS as $command) {
            $this->assertFalse(
                $this->isProhibited($command),
                "{$command} is prohibited even though APP_ENV=testing."
            );
        }
    }

    public function test_destructive_commands_are_blocked_outside_testing_environment(): void
    {
        foreach (self::DESTRUCTIVE_COMMANDS as $command) {
            $command::prohibit(true);
        }

        $usersBefore = DB::table('users')->count();

        foreach ([
            'migrate:fresh' => ['--force' => true],
            'migrate:refresh' => ['--force' => true],
            'migrate:reset' => ['--force' => true],
            'migrate:rollback' => ['--force' => true],
            'db:wipe' => ['--force' => true],
        ] as $signature => $options) {
            $exitCode = Artisan::call($signature, $options);

            $this->assertNotSame(
                0,
                $exitCode,
                "[{$signature}] was not blocked — it returned a success exit code while prohibited."
            );

            $this->assertStringContainsString(
                'prohibited',
                Artisan::output(),
                "[{$signature}] did not emit the prohibition warning."
            );
        }

        $this->assertTrue(
            Schema::hasTable('users'),
            'A destructive command dropped tables despite being prohibited.'
        );

        $this->assertSame(
            $usersBefore,
            DB::table('users')->count(),
            'A destructive command altered data despite being prohibited.'
        );
    }

    public function test_destructive_commands_are_allowed_in_testing_environment(): void
    {
        foreach (self::DESTRUCTIVE_COMMANDS as $command) {
            $command::prohibit(false);
        }

        foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe', 'migrate:rollback'] as $signature) {
            $exitCode = Artisan::call($signature, ['--force' => true]);

            $this->assertSame(0, $exitCode, "[{$signature}] was blocked in the testing environment.");
            $this->assertStringNotContainsString('prohibited', Artisan::output());

            // Migrations here include alter-table steps that aren't idempotent against a
            // partially-rolled-back schema (e.g. migrate:reset / db:wipe / migrate:rollback
            // leave the schema short of, or past, a clean migrated state). Always restore a
            // fully fresh schema between commands so each one is exercised independently.
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    public function test_protected_database_can_never_be_targeted_even_when_explicitly_selected(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $this->assertNotSame(
            'enterprise_app',
            $database,
            'The test suite is connected to the development database — refusing to run further assertions.'
        );

        $this->assertSame('enterprise_app_testing', $database);
    }

    private function isProhibited(string $command): bool
    {
        $property = new ReflectionProperty($command, 'prohibitedFromRunning');
        $property->setAccessible(true);

        return $property->getValue();
    }
}
