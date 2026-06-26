<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SessionService
{
    /**
     * Get all tracked sessions for the user, ordered by most recent.
     *
     * @return Collection<int, UserSession>
     */
    public function getSessionsForUser(User $user): Collection
    {
        return UserSession::forUser($user->id)
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * Revoke a specific session by ID.
     * Only allows revoking sessions belonging to the authenticated user.
     */
    public function revokeSession(string $sessionId, User $user, string $currentSessionId): bool
    {
        // Prevent revoking the current session through this method
        if ($sessionId === $currentSessionId) {
            return false;
        }

        $deleted = UserSession::where('session_id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted) {
            // Also delete from Laravel's sessions table
            DB::table('sessions')->where('id', $sessionId)->delete();
        }

        return (bool) $deleted;
    }

    /**
     * Revoke all sessions except the current one.
     * Returns the count of revoked sessions.
     */
    public function revokeAllOtherSessions(User $user, string $currentSessionId): int
    {
        $sessionIds = UserSession::forUser($user->id)
            ->where('session_id', '!=', $currentSessionId)
            ->pluck('session_id')
            ->toArray();

        if (empty($sessionIds)) {
            return 0;
        }

        // Remove from both tables
        UserSession::whereIn('session_id', $sessionIds)
            ->where('user_id', $user->id)
            ->delete();

        DB::table('sessions')->whereIn('id', $sessionIds)->delete();

        return count($sessionIds);
    }

    /**
     * Clean up orphaned user_sessions (where the session no longer exists).
     */
    public function pruneOrphanedSessions(): int
    {
        $validIds = DB::table('sessions')->pluck('id')->toArray();

        return UserSession::whereNotIn('session_id', $validIds)->delete();
    }
}
