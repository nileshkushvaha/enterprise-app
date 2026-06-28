<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorService
{
    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    // ── Setup ─────────────────────────────────────────────────────────

    /**
     * Generate a fresh TOTP secret and store it (unconfirmed).
     * Returns the plain secret so the controller can render a QR code.
     */
    public function enableSetup(User $user): string
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->updateQuietly([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($this->generateRecoveryCodes())),
            'two_factor_confirmed_at' => null, // not confirmed yet
        ]);

        return $secret;
    }

    /**
     * Confirm 2FA after the user submits the first valid TOTP code.
     */
    public function confirm(User $user, string $code): bool
    {
        $secret = decrypt($user->two_factor_secret);

        if (! $this->google2fa->verifyKey($secret, $code)) {
            return false;
        }

        $user->updateQuietly([
            'two_factor_confirmed_at' => now(),
        ]);

        activity('security')
            ->causedBy($user)
            ->withProperties(['ip' => request()->ip()])
            ->log('Two-factor authentication enabled');

        return true;
    }

    /**
     * Disable 2FA entirely.
     */
    public function disable(User $user): void
    {
        $user->updateQuietly([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        activity('security')
            ->causedBy($user)
            ->withProperties(['ip' => request()->ip()])
            ->log('Two-factor authentication disabled');
    }

    // ── Verification ──────────────────────────────────────────────────

    /**
     * Verify a TOTP code against the user's secret.
     * Allows a 1-step window for clock drift.
     */
    public function verifyCode(User $user, string $code): bool
    {
        $secret = decrypt($user->two_factor_secret);

        return (bool) $this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * Verify a recovery code. Consumes it if valid.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        return $user->consumeRecoveryCode($code);
    }

    // ── QR Code ───────────────────────────────────────────────────────

    /**
     * Return the OTP Auth URL to generate a QR code for.
     */
    public function qrCodeUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            company: config('app.name'),
            holder: $user->email,
            secret: $secret,
        );
    }

    /**
     * Generate a base64 SVG QR code image using chillerlan/php-qrcode.
     */
    public function qrCodeSvg(string $url): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'eccLevel' => QRCode::ECC_H,
            'imageBase64' => false,
            'moduleValues' => [
                // dark modules
                QRMatrix::M_DATA_DARK => '#6366f1',
                QRMatrix::M_FINDER_DARK => '#6366f1',
                QRMatrix::M_FINDER_DOT => '#4f46e5',
                QRMatrix::M_ALIGNMENT_DARK => '#6366f1',
                QRMatrix::M_TIMING_DARK => '#6366f1',
                QRMatrix::M_FORMAT_DARK => '#6366f1',
                QRMatrix::M_VERSION_DARK => '#6366f1',
                // light modules (background)
                QRMatrix::M_DATA => '#0f0c29',
                QRMatrix::M_FINDER => '#0f0c29',
                QRMatrix::M_ALIGNMENT => '#0f0c29',
                QRMatrix::M_SEPARATOR => '#0f0c29',
                QRMatrix::M_TIMING => '#0f0c29',
                QRMatrix::M_FORMAT => '#0f0c29',
                QRMatrix::M_VERSION => '#0f0c29',
            ],
        ]);

        return (new QRCode($options))->render($url);
    }

    // ── Recovery codes ────────────────────────────────────────────────

    /** Regenerate all 8 recovery codes for the user. */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();
        $user->updateQuietly([
            'two_factor_recovery_codes' => encrypt(json_encode($codes)),
        ]);

        return $codes;
    }

    // ── Private ───────────────────────────────────────────────────────

    private function generateRecoveryCodes(): array
    {
        return array_map(
            fn () => Str::random(10).'-'.Str::random(10),
            array_fill(0, User::RECOVERY_CODES_COUNT, null)
        );
    }
}
