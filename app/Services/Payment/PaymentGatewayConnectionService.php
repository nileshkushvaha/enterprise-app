<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Settings\PaymentGatewaySettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

final class PaymentGatewayConnectionService
{
    /**
     * @param  array<string, mixed>  $runtimeData
     */
    public function test(string $gateway, array $runtimeData, PaymentGatewaySettings $settings): GatewayConnectionResult
    {
        return match ($gateway) {
            'stripe' => $this->testStripe($runtimeData, $settings),
            'razorpay' => $this->testRazorpay($runtimeData, $settings),
            'paypal' => $this->testPayPal($runtimeData, $settings),
            'cashfree' => $this->testCashfree($runtimeData, $settings),
            'payu' => $this->testPayU($runtimeData, $settings),
            'phonepe' => $this->testPhonePe($runtimeData, $settings),
            'manual' => new GatewayConnectionResult(true, 'Manual payment does not require API connectivity.'),
            default => new GatewayConnectionResult(false, "Unsupported gateway '{$gateway}'."),
        };
    }

    /**
     * @param  array<string, mixed>  $runtimeData
     */
    private function testStripe(array $runtimeData, PaymentGatewaySettings $settings): GatewayConnectionResult
    {
        $secret = $this->resolveSecret($runtimeData['stripe_secret_key'] ?? null, $settings->stripe_secret_key);

        if (blank($secret)) {
            return new GatewayConnectionResult(false, 'Stripe secret key is missing.');
        }

        try {
            $response = Http::timeout(15)
                ->withToken($secret)
                ->acceptJson()
                ->get('https://api.stripe.com/v1/account');

            if (! $response->successful()) {
                return new GatewayConnectionResult(false, 'Stripe API rejected credentials.', [
                    'http_status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }

            return new GatewayConnectionResult(true, 'Stripe connection successful.', [
                'account_id' => $response->json('id'),
                'country' => $response->json('country'),
            ]);
        } catch (Throwable $e) {
            return new GatewayConnectionResult(false, "Stripe connection failed: {$e->getMessage()}");
        }
    }

    /**
     * @param  array<string, mixed>  $runtimeData
     */
    private function testRazorpay(array $runtimeData, PaymentGatewaySettings $settings): GatewayConnectionResult
    {
        $keyId = $runtimeData['razorpay_key_id'] ?? $settings->razorpay_key_id;
        $secret = $this->resolveSecret($runtimeData['razorpay_key_secret'] ?? null, $settings->razorpay_key_secret);

        if (blank($keyId) || blank($secret)) {
            return new GatewayConnectionResult(false, 'Razorpay Key ID or Key Secret is missing.');
        }

        try {
            $response = Http::timeout(15)
                ->withBasicAuth((string) $keyId, (string) $secret)
                ->acceptJson()
                ->get('https://api.razorpay.com/v1/items', ['count' => 1]);

            if (! $response->successful()) {
                return new GatewayConnectionResult(false, 'Razorpay API rejected credentials.', [
                    'http_status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }

            return new GatewayConnectionResult(true, 'Razorpay connection successful.');
        } catch (Throwable $e) {
            return new GatewayConnectionResult(false, "Razorpay connection failed: {$e->getMessage()}");
        }
    }

    /**
     * @param  array<string, mixed>  $runtimeData
     */
    private function testPayPal(array $runtimeData, PaymentGatewaySettings $settings): GatewayConnectionResult
    {
        $clientId = $runtimeData['paypal_client_id'] ?? $settings->paypal_client_id;
        $secret = $this->resolveSecret($runtimeData['paypal_client_secret'] ?? null, $settings->paypal_client_secret);
        $mode = $runtimeData['paypal_mode'] ?? $settings->paypal_mode;

        if (blank($clientId) || blank($secret)) {
            return new GatewayConnectionResult(false, 'PayPal Client ID or Client Secret is missing.');
        }

        $baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        try {
            $response = Http::timeout(20)
                ->asForm()
                ->withBasicAuth((string) $clientId, (string) $secret)
                ->post("{$baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if (! $response->successful()) {
                return new GatewayConnectionResult(false, 'PayPal API rejected credentials.', [
                    'http_status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }

            return new GatewayConnectionResult(true, 'PayPal connection successful.', [
                'token_type' => $response->json('token_type'),
                'expires_in' => $response->json('expires_in'),
            ]);
        } catch (Throwable $e) {
            return new GatewayConnectionResult(false, "PayPal connection failed: {$e->getMessage()}");
        }
    }

    /**
     * @param  array<string, mixed>  $runtimeData
     */
    private function testCashfree(array $runtimeData, PaymentGatewaySettings $settings): GatewayConnectionResult
    {
        $appId = $runtimeData['cashfree_app_id'] ?? $settings->cashfree_app_id;
        $secret = $this->resolveSecret($runtimeData['cashfree_secret_key'] ?? null, $settings->cashfree_secret_key);
        $environment = $runtimeData['cashfree_environment'] ?? $settings->cashfree_environment;

        if (blank($appId) || blank($secret)) {
            return new GatewayConnectionResult(false, 'Cashfree App ID or Secret Key is missing.');
        }

        $baseUrl = $environment === 'production'
            ? 'https://api.cashfree.com'
            : 'https://sandbox.cashfree.com';

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders([
                    'x-client-id' => (string) $appId,
                    'x-client-secret' => (string) $secret,
                    'x-api-version' => '2022-09-01',
                ])
                ->get("{$baseUrl}/pg/orders");

            if (! in_array($response->status(), [200, 400, 404], true)) {
                return new GatewayConnectionResult(false, 'Cashfree API rejected credentials.', [
                    'http_status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }

            return new GatewayConnectionResult(true, 'Cashfree connection successful.');
        } catch (Throwable $e) {
            return new GatewayConnectionResult(false, "Cashfree connection failed: {$e->getMessage()}");
        }
    }

    /**
     * @param  array<string, mixed>  $runtimeData
     */
    private function testPayU(array $runtimeData, PaymentGatewaySettings $settings): GatewayConnectionResult
    {
        $merchantId = $runtimeData['payu_merchant_id'] ?? $settings->payu_merchant_id;
        $privateKey = $this->resolveSecret($runtimeData['payu_private_key'] ?? null, $settings->payu_private_key);
        $sandbox = (bool) ($runtimeData['payu_sandbox_mode'] ?? $settings->payu_sandbox_mode);

        if (blank($merchantId) || blank($privateKey)) {
            return new GatewayConnectionResult(false, 'PayU Merchant ID or Private Key is missing.');
        }

        $baseUrl = $sandbox ? 'https://test.payu.in' : 'https://secure.payu.in';

        try {
            $response = Http::timeout(15)->get($baseUrl);

            if (! $response->successful()) {
                return new GatewayConnectionResult(false, 'PayU endpoint is unreachable.', [
                    'http_status' => $response->status(),
                ]);
            }

            return new GatewayConnectionResult(true, 'PayU endpoint reachable and credentials look configured.');
        } catch (Throwable $e) {
            return new GatewayConnectionResult(false, "PayU connection failed: {$e->getMessage()}");
        }
    }

    /**
     * @param  array<string, mixed>  $runtimeData
     */
    private function testPhonePe(array $runtimeData, PaymentGatewaySettings $settings): GatewayConnectionResult
    {
        $merchantId = $runtimeData['phonepe_merchant_id'] ?? $settings->phonepe_merchant_id;
        $saltKey = $this->resolveSecret($runtimeData['phonepe_salt_key'] ?? null, $settings->phonepe_salt_key);
        $sandbox = (bool) ($runtimeData['phonepe_sandbox_mode'] ?? $settings->phonepe_sandbox_mode);

        if (blank($merchantId) || blank($saltKey)) {
            return new GatewayConnectionResult(false, 'PhonePe Merchant ID or Salt Key is missing.');
        }

        $baseUrl = $sandbox
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
            : 'https://api.phonepe.com/apis/hermes';

        try {
            $response = Http::timeout(15)->get($baseUrl);

            if (! in_array($response->status(), [200, 301, 302, 401, 403, 404], true)) {
                return new GatewayConnectionResult(false, 'PhonePe endpoint is unreachable.', [
                    'http_status' => $response->status(),
                ]);
            }

            return new GatewayConnectionResult(true, 'PhonePe endpoint reachable and credentials look configured.');
        } catch (Throwable $e) {
            return new GatewayConnectionResult(false, "PhonePe connection failed: {$e->getMessage()}");
        }
    }

    private function resolveSecret(?string $runtimeValue, ?string $storedEncryptedValue): ?string
    {
        if (filled($runtimeValue)) {
            return $runtimeValue;
        }

        if (blank($storedEncryptedValue)) {
            return null;
        }

        try {
            return Crypt::decryptString($storedEncryptedValue);
        } catch (Throwable) {
            return null;
        }
    }
}
