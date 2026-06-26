<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SecurityController extends Controller
{
    /**
     * Update security notification preferences.
     */
    public function updateAlerts(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login_alerts_enabled'       => ['boolean'],
            'new_device_alerts_enabled'  => ['boolean'],
        ]);

        $request->user()->updateQuietly($validated);

        return redirect()->route('profile.show')
            ->with('active_tab', 'security')
            ->with('success', 'Security preferences updated.');
    }
}
