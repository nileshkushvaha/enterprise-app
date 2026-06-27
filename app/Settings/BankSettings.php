<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BankSettings extends Settings
{
    public bool $enable_offline_payment;
    public ?string $account_holder_name;
    public ?string $bank_name;
    public ?string $branch_name;
    public ?string $account_number;
    public ?string $ifsc_code;
    public ?string $swift_code;
    public ?string $iban;
    public string $account_type;
    public ?string $upi_id;
    public ?string $qr_code_image;
    public ?string $payment_instructions;
    public bool $display_on_invoice;
    public bool $display_on_payment_page;

    public static function group(): string
    {
        return 'payment_bank';
    }
}

