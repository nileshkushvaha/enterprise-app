<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Settings\PaymentGatewaySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

final class PaymentWebhookSignatureService
{
    /** Gateways with a real signature-verification implementation below — a blank secret must fail closed for these. */
    private const array VERIFIABLE_GATEWAYS = ['stripe', 'razorpay'];

    /** Endpoint purposes that own their own webhook secrets. */
    public const string PURPOSE_BOOKING = 'booking';

    public const string PURPOSE_PACKAGE = 'package';

    /**
     * Wallet recharge. A distinct endpoint from booking and package
     * collection, so it gets a distinct scope: a leaked recharge secret
     * must not become authority to settle lessons, and vice versa.
     */
    public const string PURPOSE_WALLET = 'wallet';

    /** @var list<string> */
    public const array PURPOSES = [self::PURPOSE_BOOKING, self::PURPOSE_PACKAGE, self::PURPOSE_WALLET];

    /**
     * @param  string|null  $purpose  which endpoint received this delivery
     *                                (self::PURPOSE_*), so a secret issued for one endpoint
     *                                cannot authenticate another. Null means "any purpose"
     *                                and should only be used by callers with no endpoint
     *                                identity of their own.
     */
    public function isValid(string $gateway, Request $request, PaymentGatewaySettings $settings, ?string $purpose = null): bool
    {
        // The local/testing provider signs with the app key. It has no
        // gateway-issued secret, but it must still be verified: an
        // unsigned settlement path is a settlement path an attacker can
        // use, and the retired Booking provider did check this.
        if ($gateway === 'fake') {
            $header = (string) $request->header('X-Booking-Payment-Signature', '');

            return $header !== '' && hash_equals(
                hash_hmac('sha256', (string) $request->getContent(), (string) config('app.key')),
                $header,
            );
        }

        $secrets = self::secretsFor($settings, $gateway, $purpose);

        if ($secrets === []) {
            // A blank secret used to fail OPEN ("safe
            // default while unconfigured") — accepting an entirely unsigned
            // request. It now fails closed for every gateway this class
            // actually knows how to verify; only a gateway with no
            // verification implemented at all (paypal/applepay — no
            // real adapter exists yet — and manual, which by definition
            // has no signature) still passes through unsigned.
            return ! in_array($gateway, self::VERIFIABLE_GATEWAYS, true);
        }

        $payload = (string) $request->getContent();

        // Every candidate is checked with a constant-time comparison and
        // the loop is NOT short-circuited on a non-match, so which
        // secret matched (and how many are configured) is not leaked
        // through response timing.
        $valid = false;

        foreach ($secrets as $secret) {
            $matches = match ($gateway) {
                'stripe' => $this->verifyStripe($request->header('Stripe-Signature'), $payload, $secret),
                'razorpay' => $this->verifyHmacHeader($request->header('X-Razorpay-Signature'), $payload, $secret),
                default => true,
            };

            $valid = $valid || $matches;
        }

        return $valid;
    }

    private function verifyStripe(?string $signatureHeader, string $payload, string $secret): bool
    {
        if (blank($signatureHeader)) {
            return false;
        }

        $parts = collect(explode(',', $signatureHeader))
            ->map(fn (string $part): array => explode('=', $part, 2))
            ->filter(fn (array $part): bool => count($part) === 2)
            ->mapWithKeys(fn (array $part): array => [trim($part[0]) => trim($part[1])]);

        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (blank($timestamp) || blank($signature)) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, (string) $signature);
    }

    private function verifyHmacHeader(?string $providedSignature, string $payload, string $secret): bool
    {
        if (blank($providedSignature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, (string) $providedSignature);
    }

    /**
     * The secrets that may authenticate a delivery to one endpoint of a
     * gateway.
     *
     * Razorpay issues a distinct secret per registered webhook, so each
     * of its three endpoints has its own settings field
     * (`razorpay_booking_webhook_secret`, `razorpay_package_webhook_secret`,
     * `razorpay_wallet_webhook_secret`) and ONLY that field is consulted:
     * a booking secret can never authenticate a package or wallet
     * delivery, and vice versa. A field may hold one secret per line so
     * the old and new value are both live during rotation.
     *
     * Gateways with a single `{gateway}_webhook_secret` field (Stripe)
     * keep the one-secret-per-line store where a line may be prefixed
     * with the endpoint it belongs to (`wallet:whsec_...`); an
     * unprefixed line applies to every endpoint of that gateway.
     *
     * A null purpose returns every secret of the gateway and is only for
     * callers with no endpoint identity (the generic log-only webhook).
     *
     * @return list<string>
     */
    public static function secretsFor(PaymentGatewaySettings $settings, string $gateway, ?string $purpose = null): array
    {
        if (self::usesDedicatedFields($gateway)) {
            $purposes = $purpose === null ? self::PURPOSES : [$purpose];
            $secrets = [];

            foreach ($purposes as $candidate) {
                $secrets = [...$secrets, ...self::dedicatedLines($settings, $gateway, $candidate)];
            }

            return collect($secrets)->unique()->values()->all();
        }

        return collect(self::lines(self::decryptSecret($settings, "{$gateway}_webhook_secret")))
            ->filter(fn (array $entry): bool => $purpose === null || $entry['scope'] === null || $entry['scope'] === $purpose)
            ->pluck('secret')
            ->unique()
            ->values()
            ->all();
    }

    /** Whether the endpoint's secret is present — for operators, never the value. */
    public static function secretState(PaymentGatewaySettings $settings, string $gateway, string $purpose): WebhookSecretState
    {
        return self::secretsFor($settings, $gateway, $purpose) === []
            ? WebhookSecretState::Missing
            : WebhookSecretState::Configured;
    }

    /** Gateways whose endpoints each own a settings field. */
    public static function usesDedicatedFields(string $gateway): bool
    {
        return $gateway === 'razorpay';
    }

    /** The dedicated settings field for one endpoint, e.g. `razorpay_booking_webhook_secret`. */
    public static function dedicatedField(string $gateway, string $purpose): string
    {
        return "{$gateway}_{$purpose}_webhook_secret";
    }

    /** @return list<string> */
    private static function dedicatedLines(PaymentGatewaySettings $settings, string $gateway, string $purpose): array
    {
        return collect(self::lines(self::decryptSecret($settings, self::dedicatedField($gateway, $purpose))))
            ->pluck('secret')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Splits a multi-line secret value. A line may name the endpoint it
     * belongs to (`wallet:whsec_aaa`); only a RECOGNISED prefix scopes a
     * line — anything else is part of the secret itself, so a secret
     * that happens to contain a colon is never truncated.
     *
     * @return list<array{scope: ?string, secret: string}>
     */
    public static function lines(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        return collect(preg_split('/\R/', (string) $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->map(function (string $line): array {
                $scope = Str::lower(Str::before($line, ':'));

                return in_array($scope, self::PURPOSES, true) && Str::contains($line, ':')
                    ? ['scope' => $scope, 'secret' => trim(Str::after($line, ':'))]
                    : ['scope' => null, 'secret' => $line];
            })
            ->filter(fn (array $entry): bool => $entry['secret'] !== '')
            ->values()
            ->all();
    }

    /** Shared with RazorpayPaymentProvider — one decrypt-with-legacy-fallback routine for all gateway secrets. */
    public static function decryptSecret(PaymentGatewaySettings $settings, string $field): ?string
    {
        $value = $settings->{$field} ?? null;

        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            // Gracefully handle already-plain values from old installs.
            return Str::startsWith((string) $value, 'eyJpdiI6') ? null : (string) $value;
        }
    }
}
