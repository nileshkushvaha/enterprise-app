<?php

declare(strict_types=1);

namespace App\Support;

final class LoginHistoryColors
{
    public static function forStatus(string $status): string
    {
        return match ($status) {
            'success' => 'success',
            'locked' => 'warning',
            'blocked' => 'danger',
            'unverified' => 'info',
            default => 'gray',
        };
    }
}
