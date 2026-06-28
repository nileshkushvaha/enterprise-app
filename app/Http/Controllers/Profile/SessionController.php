<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Profile\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SessionController extends Controller
{
    public function __construct(
        private readonly SessionService $sessionService,
    ) {}

    /**
     * Revoke a specific session.
     */
    public function revoke(Request $request, string $sessionId): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentId = $request->session()->getId();

        if ($sessionId === $currentId) {
            return $this->respond($request, false, 'You cannot revoke your current session. Use logout instead.', 422);
        }

        $revoked = $this->sessionService->revokeSession($sessionId, $user, $currentId);

        if ($request->expectsJson()) {
            return $revoked
                ? response()->json(['success' => true, 'message' => 'Session revoked.'])
                : response()->json(['success' => false, 'message' => 'Session not found or already expired.'], 404);
        }

        return redirect()->route('profile.show')
            ->with('active_tab', 'security')
            ->with($revoked ? 'success' : 'error', $revoked ? 'Session revoked successfully.' : 'Session not found.');
    }

    /**
     * Revoke all sessions except the current one.
     */
    public function revokeAll(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $current = $request->session()->getId();

        $count = $this->sessionService->revokeAllOtherSessions($user, $current);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $count > 0
                    ? "Revoked {$count} other ".str('session')->plural($count).'.'
                    : 'No other active sessions found.',
                'count' => $count,
            ]);
        }

        return redirect()->route('profile.show')
            ->with('active_tab', 'security')
            ->with('success', $count > 0
                ? "Revoked {$count} other ".str('session')->plural($count).' successfully.'
                : 'No other active sessions to revoke.');
    }

    // ── Private ───────────────────────────────────────────────────────

    private function respond(Request $request, bool $success, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => $success, 'message' => $message], $status);
        }

        return redirect()->route('profile.show')
            ->with('active_tab', 'security')
            ->with($success ? 'success' : 'error', $message);
    }
}
