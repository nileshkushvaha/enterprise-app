<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\DTOs\RecordingProviderReconciliation;
use App\Booking\Services\RecordingService;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Operator recovery for ONE recording whose meeting was replaced on
 * another provider (a cancelled Google Meet re-created on Zoom on the
 * same booking_meetings row) while the recording row kept naming the
 * old provider.
 *
 * Without --execute this is read-only: it prints the row, its meeting
 * and the decision RecordingService::reconcileProvider() would make.
 * With --execute it applies exactly that decision, audited with the
 * acting administrator — re-pointing only a row under which nothing
 * was captured, and refusing to touch an in-flight or stored transfer.
 */
final class ReconcileRecordingProvider extends Command
{
    protected $signature = 'recordings:reconcile-provider
        {recording : Recording id}
        {--admin= : Acting administrator (user id or email) — required with --execute}
        {--execute : Apply the change; without it the command only previews}';

    protected $description = 'Preview or re-point one recording to the provider of its (replaced) meeting';

    public function handle(RecordingService $recordings): int
    {
        $recording = Recording::query()->with(['bookingMeeting', 'booking'])->find((string) $this->argument('recording'));

        if ($recording === null) {
            $this->components->error('Recording not found.');

            return self::FAILURE;
        }

        $meeting = $recording->bookingMeeting;

        $this->components->twoColumnDetail('Booking', (string) ($recording->booking?->reference ?? $recording->booking_id));
        $this->components->twoColumnDetail('Recording', sprintf('%s · %s · attempts %d', $recording->provider, $recording->status->value, $recording->capture_attempts));
        $this->components->twoColumnDetail('Provider reference', $recording->provider_reference === null ? 'none' : 'present');
        $this->components->twoColumnDetail('Storage locator', $recording->storage_path === null ? 'none' : sprintf('present (%s)', (string) $recording->storage_driver));
        $this->components->twoColumnDetail('Transfer started', $recording->transfer_started_at?->toIso8601String() ?? 'never');
        $this->components->twoColumnDetail('Failure code', $recording->failure_code?->value ?? 'none');
        $this->components->twoColumnDetail('Meeting', $meeting === null ? 'none' : sprintf('%s · %s · remote id %s', $meeting->provider, $meeting->status->value, $meeting->provider_meeting_id ?? $meeting->provider_event_id ?? 'none'));

        if ($meeting !== null && $meeting->provider === $recording->provider) {
            $this->components->info('Recording and meeting already agree. Nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('execute')) {
            $this->components->warn('Preview only — nothing was changed. Re-run with --execute --admin=<user> to apply.');
            $this->components->twoColumnDetail('Would', $this->describe($recording));

            return self::SUCCESS;
        }

        $adminRef = trim((string) $this->option('admin'));
        $admin = $adminRef === '' ? null : User::query()->where('email', $adminRef)->orWhere('id', $adminRef)->first();

        if ($admin === null) {
            $this->components->error('--admin=<user id or email> is required with --execute and must name an existing administrator.');

            return self::FAILURE;
        }

        $outcome = $recordings->reconcileProvider($recording, audit: true, admin: $admin);

        return match ($outcome->decision) {
            RecordingProviderReconciliation::REALIGNED => $this->report(
                sprintf('Recording re-pointed from %s to %s; status pending, attempts reset. The next capture (sweep or job) fetches from %s.', (string) $outcome->previousProvider, (string) $outcome->meetingProvider, (string) $outcome->meetingProvider),
                self::SUCCESS,
            ),
            RecordingProviderReconciliation::ALIGNED => $this->report('Recording and meeting already agree. Nothing changed.', self::SUCCESS),
            default => $this->report('Protected — nothing changed: '.(string) $outcome->reason, self::FAILURE),
        };
    }

    private function describe(Recording $recording): string
    {
        $nothingCaptured = in_array($recording->status->value, ['pending', 'failed'], true) && $recording->storage_path === null;
        $meetingLive = $recording->bookingMeeting?->status->value === 'created';

        if ($nothingCaptured && $meetingLive) {
            return sprintf('re-point to %s, reset attempts to 0, clear the old provider reference', (string) $recording->bookingMeeting?->provider);
        }

        return 'refuse: '.($meetingLive ? 'an in-flight or stored transfer is never relabelled' : 'the meeting is not in a created state');
    }

    private function report(string $message, int $code): int
    {
        $code === self::SUCCESS ? $this->components->info($message) : $this->components->error($message);

        return $code;
    }
}
