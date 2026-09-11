<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Services\Payment\PaymentWebhookSignatureService;
use App\Services\Payment\WebhookSecretState;
use App\Settings\PaymentGatewaySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Tests\TestCase;

/**
 * The additive split of the legacy multi-line Razorpay webhook secret
 * into one field per endpoint. Scoped lines move; an unscoped shared
 * line is deliberately NOT copied into three fields — that would
 * declare three endpoints configured on the strength of one value.
 */
final class RazorpayPurposeWebhookSecretMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const string FILE = 'database/settings/2026_09_11_100000_add_razorpay_purpose_webhook_secrets.php';

    private function runMigrationAgainst(?string $legacyPlain): PaymentGatewaySettings
    {
        $settings = app(PaymentGatewaySettings::class);
        $settings->razorpay_webhook_secret = $legacyPlain === null ? null : Crypt::encryptString($legacyPlain);
        $settings->save();

        // RefreshDatabase already ran the migration once; remove its
        // three properties so up() starts from the pre-migration shape.
        $migrator = app(SettingsMigrator::class);

        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            $migrator->deleteIfExists('payment_gateways.'.PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose));
        }

        /** @var SettingsMigration $migration */
        $migration = require base_path(self::FILE);
        $migration->up();

        return app(PaymentGatewaySettings::class)->refresh();
    }

    public function test_scoped_legacy_lines_are_copied_into_their_dedicated_fields(): void
    {
        $settings = $this->runMigrationAgainst("booking:whsec_b\npackage:whsec_p\nwallet:whsec_w");

        $this->assertSame('whsec_b', PaymentWebhookSignatureService::decryptSecret($settings, 'razorpay_booking_webhook_secret'));
        $this->assertSame('whsec_p', PaymentWebhookSignatureService::decryptSecret($settings, 'razorpay_package_webhook_secret'));
        $this->assertSame('whsec_w', PaymentWebhookSignatureService::decryptSecret($settings, 'razorpay_wallet_webhook_secret'));

        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            $this->assertSame(WebhookSecretState::Configured, PaymentWebhookSignatureService::secretState($settings, 'razorpay', $purpose));
        }

        // The legacy field is left intact so a rollback loses nothing.
        $this->assertSame("booking:whsec_b\npackage:whsec_p\nwallet:whsec_w", PaymentWebhookSignatureService::decryptSecret($settings, 'razorpay_webhook_secret'));
    }

    public function test_rotation_pairs_stay_together(): void
    {
        $settings = $this->runMigrationAgainst("booking:old_b\nbooking:new_b\nwallet:whsec_w");

        $this->assertSame("old_b\nnew_b", PaymentWebhookSignatureService::decryptSecret($settings, 'razorpay_booking_webhook_secret'));
        $this->assertNull($settings->razorpay_package_webhook_secret);
        $this->assertSame(WebhookSecretState::Missing, PaymentWebhookSignatureService::secretState($settings, 'razorpay', PaymentWebhookSignatureService::PURPOSE_PACKAGE));
    }

    /** One shared secret is NOT proof of three configured endpoints. */
    public function test_an_unscoped_legacy_secret_is_preserved_but_not_promoted(): void
    {
        $settings = $this->runMigrationAgainst('one_shared_secret');

        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            $this->assertNull($settings->{PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose)});
            $this->assertSame(WebhookSecretState::LegacyUnscoped, PaymentWebhookSignatureService::secretState($settings, 'razorpay', $purpose));
        }

        // ...and it still verifies every endpoint, exactly as before.
        $this->assertSame(['one_shared_secret'], PaymentWebhookSignatureService::decryptSecrets($settings, 'razorpay_webhook_secret', PaymentWebhookSignatureService::PURPOSE_BOOKING));
    }

    public function test_a_blank_legacy_field_creates_empty_dedicated_fields(): void
    {
        $settings = $this->runMigrationAgainst(null);

        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            $this->assertNull($settings->{PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose)});
            $this->assertSame(WebhookSecretState::Missing, PaymentWebhookSignatureService::secretState($settings, 'razorpay', $purpose));
        }
    }
}
