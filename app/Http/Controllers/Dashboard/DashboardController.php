<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardResolver $resolver,
    ) {}

    public function __invoke(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($this->resolver->isAdminPanel($user)) {
            return redirect('/admin');
        }

        return view('dashboard.index', [
            'frontendMenu' => $this->resolver->frontendMenu($user),
        ]);
    }
}
