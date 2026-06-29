<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserPasswordHistory;
use App\Settings\PasswordPolicySettings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class PasswordHistoryService
{
    public function __construct(
        private readonly PasswordPolicySettings $settings,
    ) {}

    public function isReused(User $user, string $newPlainPassword): bool
    {
        if (! $this->settings->prevent_reuse || $this->settings->password_history_count <= 0) {
            return false;
        }

        $histories = UserPasswordHistory::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($this->settings->password_history_count)
            ->get();

        foreach ($histories as $history) {
            if (Hash::check($newPlainPassword, $history->password_hash)) {
                return true;
            }
        }

        return false;
    }

    public function assertNotReused(User $user, string $newPlainPassword): void
    {
        if ($this->isReused($user, $newPlainPassword)) {
            throw ValidationException::withMessages([
                'password' => [
                    "You cannot reuse a password from your last {$this->settings->password_history_count} passwords.",
                ],
            ]);
        }
    }

    public function store(User $user, string $previousHash): void
    {
        if (! $previousHash) {
            return;
        }

        UserPasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => $previousHash,
        ]);

        // Keep only the most recent N entries per user
        $limit = max(1, $this->settings->password_history_count);

        $idsToKeep = UserPasswordHistory::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id');

        UserPasswordHistory::where('user_id', $user->id)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }
}
