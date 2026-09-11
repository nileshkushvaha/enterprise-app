<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Contracts\ZoomMeetingClient;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Booking\Repositories\MeetingHostReservationRepository;
use App\Settings\MeetingSettings;
use Illuminate\Console\Command;

/**
 * Staging readiness for Zoom WITHOUT creating anything at Zoom.
 *
 * Reports configured / not configured for every Zoom setting SIRI reads,
 * mints one Server-to-Server OAuth token (ZoomMeetingClient::
 * validateCredentials(), which clears the token cache first) and states
 * whether it was acquired. Prints no secret and no token. The admin
 * panel's "Validate Zoom Configuration" action goes one step further —
 * it creates and deletes a temporary meeting — so use THIS first, and
 * that only once a real meeting is acceptable on the staging account.
 *
 * Token minting does not exercise scopes; the scopes the app actually
 * needs are listed in docs/deployment/zoom-activation.md §2 and are
 * confirmed by the first real meeting (§7).
 */
final class CheckZoomCredentials extends Command
{
    protected $signature = 'meetings:zoom:check-auth';

    protected $description = 'Report Zoom settings as configured/not configured and mint one OAuth token — creates no meeting, prints no secret';

    public function handle(MeetingSettings $settings, ZoomMeetingClient $client, MeetingHostReservationRepository $hosts): int
    {
        $this->components->twoColumnDetail('Meetings enabled', $settings->meetings_enabled ? 'yes' : '<fg=yellow>no</>');
        $this->components->twoColumnDetail('Default provider', $settings->default_provider.($settings->default_provider === ZoomMeetingProvider::KEY ? '' : '  (Zoom is not the default — correct for a first staging test)'));
        $this->components->twoColumnDetail('zoom_enabled', $settings->zoom_enabled ? 'yes' : '<fg=yellow>no</>');
        $this->components->twoColumnDetail('zoom_account_id', $this->state(filled($settings->zoom_account_id)));
        $this->components->twoColumnDetail('zoom_client_id', $this->state(filled($settings->zoom_client_id)));
        $this->components->twoColumnDetail('zoom_client_secret', $this->state($settings->decryptedZoomClientSecret() !== null, 'configured (decryptable)'));
        $this->components->twoColumnDetail('zoom_host_user_id / zoom_host_email', $this->state(filled($settings->zoom_host_user_id) || filled($settings->zoom_host_email)));
        $this->components->twoColumnDetail('zoom_webhook_secret', $this->state($settings->decryptedZoomWebhookSecret() !== null, 'configured (decryptable)'));
        $this->components->twoColumnDetail('zoom_recording_enabled', $settings->zoom_recording_enabled ? 'yes' : 'no');
        $this->components->twoColumnDetail('zoom_recording_webhooks_enabled', $settings->zoom_recording_webhooks_enabled ? 'yes' : 'no');
        $this->components->twoColumnDetail('zoom_host_capacity_enabled', $settings->zoom_host_capacity_enabled ? 'yes' : '<fg=yellow>no</>');

        $pool = $hosts->activePool(ZoomMeetingProvider::KEY);
        $this->components->twoColumnDetail('Registered platform host(s)', $pool->isEmpty() ? '<fg=yellow>none (meetings:zoom-hosts:register)</>' : $pool->map(fn ($host): string => sprintf('%s (capacity %d)', $host->host_reference, $host->capacity))->implode(', '));

        $credentialsComplete = filled($settings->zoom_account_id)
            && filled($settings->zoom_client_id)
            && $settings->decryptedZoomClientSecret() !== null;

        if (! $credentialsComplete) {
            $this->components->error('Credentials incomplete — token minting skipped.');

            return self::FAILURE;
        }

        // One network call: POST https://zoom.us/oauth/token (account_credentials grant). Nothing is created.
        $ok = $client->validateCredentials();
        $this->components->twoColumnDetail('OAuth token acquired', $ok ? '<fg=green>yes</>' : '<fg=red>NO</>');

        if (! $ok) {
            $this->components->error('Zoom refused the Server-to-Server OAuth credentials. Check Account ID, Client ID and Client Secret in Settings → Meetings; the app must be activated in the Zoom Marketplace.');

            return self::FAILURE;
        }

        $this->components->info('Authentication verified without creating a meeting. Scopes are exercised only by real API calls — see docs/deployment/zoom-activation.md §2 for the exact list.');

        return self::SUCCESS;
    }

    private function state(bool $configured, string $yes = 'configured'): string
    {
        return $configured ? $yes : '<fg=yellow>not configured</>';
    }
}
