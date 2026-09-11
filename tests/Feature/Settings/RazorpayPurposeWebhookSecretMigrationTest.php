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
 * The two-step replacement of the old multi-line Razorpay webhook
 * secret with one field per endpoint: the split migration copies
 * prefixed lines into their endpoint's field, the removal migration
 * drops the old field once all three exist. An unprefixed line cannot
 * be attributed and is not carried over.
 */
final class RazorpayPurposeWebhookSecretMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const string SPLIT = 'database/settings/2026_09_11_100000_add_razorpay_purpose_webhook_secrets.php';

    private const string REMOVE = 'database/settings/2026_09_11_100100_remove_legacy_razorpay_webhook_secret.php';

    private const string LEGACY = 'payment_gateways.razorpay_webhook_secret';

    private function runMigrationAgainst(?string $legacyPlain): PaymentGatewaySettings
    {
        // RefreshDatabase already ran the migration once; rebuild the
        // pre-migration shape — the old field present, the new ones absent.
        $migrator = app(SettingsMigrator::class);
        $migrator->deleteIfExists(self::LEGACY);
        $migrator->add(self::LEGACY, $legacyPlain === null ? null : Crypt::encryptString($legacyPlain));

        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            $migrator->deleteIfExists('payment_gateways.'.PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose));
        }

        $this->runBoth();

        return app(PaymentGatewaySettings::class)->refresh();
    }

    /** The split migration, then the removal — the order a deploy runs them in. */
    private function runBoth(): void
    {
        foreach ([self::SPLIT, self::REMOVE] as $file) {
            /** @var SettingsMigration $migration */
            $migration = require base_path($file);
            $migration->up();
        }
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

        $this->assertFalse(app(SettingsMigrator::class)->exists(self::LEGACY), 'the old shared field is removed');
    }

    public function test_rotation_pairs_stay_together(): void
    {
        $settings = $this->runMigrationAgainst("booking:old_b\nbooking:new_b\nwallet:whsec_w");

        $this->assertSame("old_b\nnew_b", PaymentWebhookSignatureService::decryptSecret($settings, 'razorpay_booking_webhook_secret'));
        $this->assertNull($settings->razorpay_package_webhook_secret);
        $this->assertSame(WebhookSecretState::Missing, PaymentWebhookSignatureService::secretState($settings, 'razorpay', PaymentWebhookSignatureService::PURPOSE_PACKAGE));
    }

    /** One shared secret cannot be attributed to an endpoint, so it is not promoted; the operator re-enters each. */
    public function test_an_unprefixed_shared_secret_is_not_promoted_to_any_endpoint(): void
    {
        $settings = $this->runMigrationAgainst('one_shared_secret');

        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            $this->assertNull($settings->{PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose)});
            $this->assertSame(WebhookSecretState::Missing, PaymentWebhookSignatureService::secretState($settings, 'razorpay', $purpose));
        }

        $this->assertFalse(app(SettingsMigrator::class)->exists(self::LEGACY));
    }

    public function test_running_twice_is_harmless(): void
    {
        $this->runMigrationAgainst('booking:whsec_b');
        $this->runBoth();

        $settings = app(PaymentGatewaySettings::class)->refresh();
        $this->assertSame('whsec_b', PaymentWebhookSignatureService::decryptSecret($settings, 'razorpay_booking_webhook_secret'));
    }

    public function test_a_blank_legacy_field_creates_empty_dedicated_fields(): void
    {
        $settings = $this->runMigrationAgainst(null);

        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            $this->assertNull($settings->{PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose)});
            $this->assertSame(WebhookSecretState::Missing, PaymentWebhookSignatureService::secretState($settings, 'razorpay', $purpose));
        }
    }

    /** The removal never runs ahead of the split: without the three fields the old one is kept. */
    public function test_the_removal_waits_for_the_dedicated_fields(): void
    {
        $migrator = app(SettingsMigrator::class);
        $migrator->deleteIfExists(self::LEGACY);
        $migrator->add(self::LEGACY, Crypt::encryptString('booking:whsec_b'));
        $migrator->deleteIfExists('payment_gateways.'.PaymentWebhookSignatureService::dedicatedField('razorpay', PaymentWebhookSignatureService::PURPOSE_WALLET));

        /** @var SettingsMigration $migration */
        $migration = require base_path(self::REMOVE);
        $migration->up();

        $this->assertTrue($migrator->exists(self::LEGACY));

        // Restore the schema the settings class expects for the rest of the process.
        $migrator->add('payment_gateways.'.PaymentWebhookSignatureService::dedicatedField('razorpay', PaymentWebhookSignatureService::PURPOSE_WALLET), null);
    }
}
