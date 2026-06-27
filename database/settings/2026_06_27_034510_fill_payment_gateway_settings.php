<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $gateways = [
            'stripe' => ['enabled' => false, 'sandbox' => true, 'mode' => null],
            'razorpay' => ['enabled' => false, 'sandbox' => true, 'mode' => null],
            'paypal' => ['enabled' => false, 'sandbox' => null, 'mode' => 'sandbox'],
            'cashfree' => ['enabled' => false, 'sandbox' => null, 'mode' => 'sandbox'],
            'payu' => ['enabled' => false, 'sandbox' => true, 'mode' => null],
            'phonepe' => ['enabled' => false, 'sandbox' => true, 'mode' => null],
        ];

        foreach ($gateways as $gateway => $config) {
            $this->migrator->add("payment_gateways.{$gateway}_enabled", $config['enabled']);

            if ($config['sandbox'] !== null) {
                $this->migrator->add("payment_gateways.{$gateway}_sandbox_mode", $config['sandbox']);
            }

            if ($config['mode'] !== null) {
                $key = $gateway === 'cashfree' ? 'environment' : 'mode';
                $this->migrator->add("payment_gateways.{$gateway}_{$key}", $config['mode']);
            }

            $this->migrator->add("payment_gateways.{$gateway}_success_url", null);
            $this->migrator->add("payment_gateways.{$gateway}_failure_url", null);
            $this->migrator->add("payment_gateways.{$gateway}_webhook_url", null);
        }

        $this->migrator->add('payment_gateways.stripe_publishable_key', null);
        $this->migrator->add('payment_gateways.stripe_secret_key', null);
        $this->migrator->add('payment_gateways.stripe_webhook_secret', null);

        $this->migrator->add('payment_gateways.razorpay_key_id', null);
        $this->migrator->add('payment_gateways.razorpay_key_secret', null);
        $this->migrator->add('payment_gateways.razorpay_webhook_secret', null);

        $this->migrator->add('payment_gateways.paypal_client_id', null);
        $this->migrator->add('payment_gateways.paypal_client_secret', null);
        $this->migrator->add('payment_gateways.paypal_webhook_secret', null);

        $this->migrator->add('payment_gateways.cashfree_app_id', null);
        $this->migrator->add('payment_gateways.cashfree_secret_key', null);
        $this->migrator->add('payment_gateways.cashfree_webhook_secret', null);

        $this->migrator->add('payment_gateways.payu_merchant_id', null);
        $this->migrator->add('payment_gateways.payu_public_key', null);
        $this->migrator->add('payment_gateways.payu_private_key', null);
        $this->migrator->add('payment_gateways.payu_webhook_secret', null);

        $this->migrator->add('payment_gateways.phonepe_merchant_id', null);
        $this->migrator->add('payment_gateways.phonepe_salt_key', null);
        $this->migrator->add('payment_gateways.phonepe_salt_index', null);
        $this->migrator->add('payment_gateways.phonepe_webhook_secret', null);

        $this->migrator->add('payment_gateways.manual_enabled', true);
        $this->migrator->add('payment_gateways.manual_payment_instructions', null);
    }
};
