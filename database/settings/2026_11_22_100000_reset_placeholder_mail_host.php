<?php

use App\Settings\MailSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Hands SMTP configuration back to .env on installs that never set a host.
     *
     * 2026_06_26_233120_fill_mail_settings.php seeded `mail.host` with the
     * placeholder 'smtp.mailtrap.io'. Because
     * AppServiceProvider::applySettingsDrivenMailTransport() merges the stored
     * host over config('mail.mailers.smtp') on every boot, any install whose
     * administrator never opened Admin → Settings → Mail has been quietly
     * sending through Mailtrap while .env said otherwise — and the SMTP section
     * was hidden for anyone on the "inherit MAIL_MAILER" driver, so there was no
     * field on screen to reveal it.
     *
     * Blank now means "inherit the SMTP connection from .env", so clearing the
     * placeholder restores MAIL_HOST as the source of truth.
     *
     * Only the untouched placeholder is cleared. A host an administrator
     * actually chose is a real configuration and is left exactly as it is —
     * including a deliberate Mailtrap host on a staging box, which will have a
     * username and password stored alongside it.
     */
    public function up(): void
    {
        $this->migrator->update('mail.host', function (string $host): string {
            if ($host !== 'smtp.mailtrap.io') {
                return $host;
            }

            $settings = app(MailSettings::class);

            return blank($settings->username) && blank($settings->password) ? '' : $host;
        });
    }
};
