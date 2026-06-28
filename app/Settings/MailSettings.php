<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    // Sender
    public string $from_name;

    public string $from_email;

    // SMTP
    public string $driver;

    public string $host;

    public int $port;

    public ?string $username;

    public ?string $password;  // stored encrypted

    public string $encryption;

    // Queue
    public bool $queue_emails;

    // Advanced
    public int $connection_timeout;

    public int $retry_attempts;

    public static function group(): string
    {
        return 'mail';
    }
}
