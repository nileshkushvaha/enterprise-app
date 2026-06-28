<?php

declare(strict_types=1);

namespace App\Jobs\Payments;

use App\Services\Payment\PaymentWebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        public readonly string $gateway,
        /** @var array<string, mixed> */
        public readonly array $payload,
        /** @var array<string, string> */
        public readonly array $headers,
    ) {
        $this->onQueue('payment-webhooks');
    }

    public function handle(PaymentWebhookProcessor $processor): void
    {
        $processor->process($this->gateway, $this->payload, $this->headers);
    }
}
