<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Filament\Resources\EmailLogs\Pages\ViewEmailLog;
use App\Models\EmailLog;
use App\Models\User;
use App\Services\Mail\EmailLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailLogViewPageTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole('super_admin');

        return $user;
    }

    // ── The provider label ─────────────────────────────────────────────────

    public function test_the_recorded_provider_follows_the_configured_transport(): void
    {
        // It used to be hardcoded to 'resend' whenever the app was in
        // production. The moment the deployment switched to an SMTP
        // relay, the audit log started describing every message as
        // Resend while the adjacent `mailer` column said otherwise.
        $service = app(EmailLogService::class);
        $provider = new \ReflectionMethod($service, 'provider');

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);
        $this->assertSame('smtp', $provider->invoke($service));

        config(['mail.default' => 'resend', 'mail.mailers.resend.transport' => 'resend']);
        $this->assertSame('resend', $provider->invoke($service));
    }

    public function test_the_provider_is_not_forced_to_resend_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);

        $service = app(EmailLogService::class);
        $provider = new \ReflectionMethod($service, 'provider');

        $this->assertSame('smtp', $provider->invoke($service), 'production must not override the real transport');
    }

    private function log(array $overrides = []): EmailLog
    {
        $id = (string) Str::uuid();

        return EmailLog::query()->create([
            'id' => $id,
            'notification_id' => $id,
            'notification_type' => 'App\\Notifications\\Booking\\BookingConfirmedNotification',
            'category' => 'general',
            'provider' => 'resend',
            'mailer' => 'smtp',
            'status' => 'sent',
            'subject' => 'SIRI SMTP Test',
            'from_address' => 'no-reply@sirieducation.com',
            'from_name' => 'SIRI Education',
            // The shape the service actually writes: a LIST of
            // {address, name} objects, not a flat map.
            'to' => [['address' => 'someone@example.com', 'name' => 'Someone']],
            'cc' => [],
            'bcc' => [],
            'queued' => false,
            'provider_message_id' => 'cc2b18613ff87d010143d6c8b2ea5c5c',
            'attempts' => 1,
            'metadata' => ['laravel_mail_data_keys' => ['__laravel_notification']],
            'sent_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_the_view_page_renders_a_logged_email(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ViewEmailLog::class, ['record' => $this->log()->getKey()])
            ->assertOk()
            ->assertSee('SIRI SMTP Test')
            ->assertSee('someone@example.com');
    }

    public function test_the_view_page_renders_an_email_with_several_recipients(): void
    {
        $this->actingAs($this->superAdmin());

        $log = $this->log([
            'to' => [
                ['address' => 'first@example.com', 'name' => 'First'],
                ['address' => 'second@example.com', 'name' => ''],
            ],
            'cc' => [['address' => 'cc@example.com', 'name' => 'Copied']],
        ]);

        Livewire::test(ViewEmailLog::class, ['record' => $log->getKey()])
            ->assertOk()
            ->assertSee('first@example.com')
            ->assertSee('second@example.com')
            ->assertSee('cc@example.com');
    }

    public function test_the_view_page_renders_nested_metadata(): void
    {
        // payloadFromMessage() always writes a NESTED array here
        // (laravel_mail_data_keys => [...]), so this is the shape of
        // every real message, not an edge case.
        $this->actingAs($this->superAdmin());

        $log = $this->log([
            'to' => [],
            'metadata' => ['laravel_mail_data_keys' => ['__laravel_notification', '__laravel_notification_id']],
        ]);

        Livewire::test(ViewEmailLog::class, ['record' => $log->getKey()])->assertOk();
    }

    public function test_the_view_page_renders_when_there_are_no_recipients_recorded(): void
    {
        $this->actingAs($this->superAdmin());

        $log = $this->log(['to' => [], 'cc' => [], 'bcc' => [], 'metadata' => []]);

        Livewire::test(ViewEmailLog::class, ['record' => $log->getKey()])
            ->assertOk();
    }
}
