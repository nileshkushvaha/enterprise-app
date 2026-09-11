<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Booking\Services\RecordingDeliveryService;
use App\Http\Controllers\Controller;
use App\Models\Recording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administrative download of the ORIGINAL recording file as an
 * attachment (SRS §12.20 — administrator access).
 *
 * Lives under /admin because the people who hold View:Recording use
 * the admin portal and are redirected away from /dashboard/* by
 * EnsureFrontendPortal; the student portal has its own, narrower
 * playback routes (RecordingWatchController / RecordingStreamController)
 * and no download. One delivery service, two portal entry points —
 * not a second authorization system.
 *
 * Every request re-checks RecordingPolicy::download() live, so a
 * bookmarked or forwarded link is worthless without a currently
 * authenticated, currently permitted session. The response never
 * reveals where the bytes live: the storage locator is resolved
 * server-side and the content is proxied back as a stream.
 */
final class RecordingDownloadController extends Controller
{
    public function __invoke(Request $request, Recording $recording, RecordingDeliveryService $delivery): Response
    {
        // 'download' is stricter than 'view': it additionally requires
        // the recording to still HAVE a stored object, so an expired or
        // failed recording is never half-served.
        Gate::authorize('download', $recording);

        // Business state is not authorization: Gate::before lets a super
        // admin through the policy, but a recording without a verified
        // stored object (failed, transferring, expired — or a failed row
        // still holding a preserved locator) has no downloadable file.
        // A plain 404, never a backend detail.
        abort_unless($recording->isPlayable(), 404, 'This recording has no downloadable file.');

        // A missing or unreadable object behind an Available row is
        // reported by the delivery service as a generic 503 and logged
        // for operators; the response never carries a URL or exception.
        return $delivery->respond($recording, null, inline: false, headOnly: $request->isMethod('HEAD'));
    }
}
