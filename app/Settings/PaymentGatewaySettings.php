<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PaymentGatewaySettings extends Settings
{
    public bool $stripe_enabled;

    public bool $stripe_sandbox_mode;

    public ?string $stripe_publishable_key;

    public ?string $stripe_secret_key;

    public ?string $stripe_webhook_secret;

    public ?string $stripe_success_url;

    public ?string $stripe_failure_url;

    public ?string $stripe_webhook_url;

    public bool $razorpay_enabled;

    public bool $razorpay_sandbox_mode;

    public ?string $razorpay_key_id;

    public ?string $razorpay_key_secret;

    public ?string $razorpay_webhook_secret;

    public ?string $razorpay_success_url;

    public ?string $razorpay_failure_url;

    public ?string $razorpay_webhook_url;

    public bool $paypal_enabled;

    public string $paypal_mode;

    public ?string $paypal_client_id;

    public ?string $paypal_client_secret;

    public ?string $paypal_webhook_secret;

    public ?string $paypal_success_url;

    public ?string $paypal_failure_url;

    public ?string $paypal_webhook_url;

    public bool $cashfree_enabled;

    public string $cashfree_environment;

    public ?string $cashfree_app_id;

    public ?string $cashfree_secret_key;

    public ?string $cashfree_webhook_secret;

    public ?string $cashfree_success_url;

    public ?string $cashfree_failure_url;

    public ?string $cashfree_webhook_url;

    public bool $payu_enabled;

    public bool $payu_sandbox_mode;

    public ?string $payu_merchant_id;

    public ?string $payu_public_key;

    public ?string $payu_private_key;

    public ?string $payu_webhook_secret;

    public ?string $payu_success_url;

    public ?string $payu_failure_url;

    public ?string $payu_webhook_url;

    public bool $phonepe_enabled;

    public bool $phonepe_sandbox_mode;

    public ?string $phonepe_merchant_id;

    public ?string $phonepe_salt_key;

    public ?string $phonepe_salt_index;

    public ?string $phonepe_webhook_secret;

    public ?string $phonepe_success_url;

    public ?string $phonepe_failure_url;

    public ?string $phonepe_webhook_url;

    public bool $manual_enabled;

    public ?string $manual_payment_instructions;

    public static function group(): string
    {
        return 'payment_gateways';
    }
}
