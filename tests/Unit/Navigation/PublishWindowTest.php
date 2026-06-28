<?php

declare(strict_types=1);

namespace Tests\Unit\Navigation;

use App\Navigation\DTOs\PublishWindow;
use Carbon\Carbon;
use Tests\TestCase;

class PublishWindowTest extends TestCase
{
    public function test_always_is_always_active(): void
    {
        $window = PublishWindow::always();

        $this->assertTrue($window->isActive());
        $this->assertFalse($window->isConstrained());
    }

    public function test_active_when_within_window(): void
    {
        $window = new PublishWindow(
            startsAt: Carbon::now()->subHour(),
            endsAt: Carbon::now()->addHour(),
        );

        $this->assertTrue($window->isActive());
    }

    public function test_inactive_before_start(): void
    {
        $window = new PublishWindow(
            startsAt: Carbon::now()->addHour(),
            endsAt: null,
        );

        $this->assertFalse($window->isActive());
    }

    public function test_inactive_after_end(): void
    {
        $window = new PublishWindow(
            startsAt: null,
            endsAt: Carbon::now()->subHour(),
        );

        $this->assertFalse($window->isActive());
    }

    public function test_active_with_only_start_set_and_past(): void
    {
        $window = new PublishWindow(
            startsAt: Carbon::now()->subDay(),
            endsAt: null,
        );

        $this->assertTrue($window->isActive());
    }

    public function test_active_with_only_end_set_and_future(): void
    {
        $window = new PublishWindow(
            startsAt: null,
            endsAt: Carbon::now()->addDay(),
        );

        $this->assertTrue($window->isActive());
    }

    public function test_is_constrained_true_when_dates_set(): void
    {
        $window = new PublishWindow(Carbon::now(), null);
        $this->assertTrue($window->isConstrained());

        $window2 = new PublishWindow(null, Carbon::now());
        $this->assertTrue($window2->isConstrained());
    }

    public function test_from_factory_method(): void
    {
        $start = Carbon::now()->subHour();
        $end = Carbon::now()->addHour();
        $window = PublishWindow::from($start, $end);

        $this->assertSame($start, $window->startsAt);
        $this->assertSame($end, $window->endsAt);
    }

    public function test_active_check_uses_provided_now(): void
    {
        $window = new PublishWindow(
            startsAt: Carbon::parse('2030-01-01'),
            endsAt: Carbon::parse('2030-12-31'),
        );

        $this->assertTrue($window->isActive(Carbon::parse('2030-06-15')));
        $this->assertFalse($window->isActive(Carbon::parse('2029-12-31')));
        $this->assertFalse($window->isActive(Carbon::parse('2031-01-01')));
    }
}
