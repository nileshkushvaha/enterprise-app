<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\PortalResolver;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly PortalResolver $portal,
    ) {}

    /**
     * Admin-portal users are kept off this route entirely by the
     * frontend.portal middleware (see routes/web.php) — by the time this
     * runs, the user is guaranteed to belong to the Frontend Portal.
     */
    public function __invoke(): View
    {
        $user = auth()->user();

        return view('dashboard.index', [
            'frontendMenu' => $this->portal->frontendMenu($user),
        ]);
    }
}
