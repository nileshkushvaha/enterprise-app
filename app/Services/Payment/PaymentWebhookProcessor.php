<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Settings\PaymentAdvancedSettings;
use Illuminate\Support\Facades\Log;

final class PaymentWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function process(string $gateway, array $payload, array $headers): void
    {
        $advanced = app(PaymentAdvancedSettings::class);

        if ($advanced->payment_logging) {
            Log::channel(config('logging.default'))->info('Payment webhook received', [
                'gateway' => $gateway,
                'event' => $payload['type'] ?? $payload['event'] ?? null,
                'headers' => $headers,
                'payload' => $payload,
            ]);
        }

        if ($advanced->enable_audit_log && function_exists('activity')) {
            activity('payments')
                ->withProperties([
                    'gateway' => $gateway,
                    'event' => $payload['type'] ?? $payload['event'] ?? null,
                    'payload' => $payload,
                ])
                ->log('payment.webhook.received');
        }

        // Hook point: domain-specific payment reconciliation can be added here.
    }
}
