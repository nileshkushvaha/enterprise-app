<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\DTOs\NotificationPayload;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

/**
 * Sole responsibility: persist Filament database notifications for admin recipients.
 *
 * What this service does NOT do:
 *  - Decide which activities are important (NotificationMapper does that)
 *  - Resolve URLs (ActivityUrlResolver does that)
 *  - Know anything about the Activity Log
 *
 * Future extension points:
 *  - Email: inject a mailer and call it here alongside sendToDatabase
 *  - Slack/Teams: add a SlackNotifier and dispatch from this method
 *  - User preferences: filter $recipients before sending based on a preference table
 *  - Digest: enqueue payloads and flush on a schedule instead of sending immediately
 */
final class AdminNotificationService
{
    /**
     * Resolve super_admin recipients and persist the Filament database notification.
     *
     * @param  User|null  $actor  The user who caused the activity. They are excluded
     *                            from recipients when they are already a super_admin
     *                            (no one needs a notification about their own action).
     */
    public function notify(NotificationPayload $payload, ?User $actor = null): void
    {
        $recipients = $this->resolveRecipients($actor);

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = Notification::make()
            ->title($payload->title)
            ->body($payload->body)
            ->icon($payload->icon)
            ->color($payload->color);

        if ($payload->url) {
            $notification->actions([
                Action::make('view')
                    ->label('View')
                    ->url($payload->url)
                    ->button(),
            ]);
        }

        $notification->sendToDatabase($recipients);
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * Returns all active super_admins, optionally excluding the actor.
     * Never hardcodes user IDs — always resolves by role name.
     */
    private function resolveRecipients(?User $actor): Collection
    {
        try {
            return User::role('super_admin')
                ->where('status', User::STATUS_ACTIVE)
                ->when($actor?->id && $actor->isSuperAdmin(), fn ($q) => $q->where('id', '!=', $actor->id))
                ->get();
        } catch (RoleDoesNotExist) {
            return new Collection;
        }
    }
}
