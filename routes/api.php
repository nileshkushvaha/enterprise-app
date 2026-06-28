<?php

declare(strict_types=1);

use App\Http\Controllers\Payments\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->name('api.payments.webhooks.handle');
