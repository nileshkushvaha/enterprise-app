<?php

declare(strict_types=1);

namespace App\Http\Controllers\Faq;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Faq\FaqService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardFaqController extends Controller
{
    public function __construct(
        private readonly FaqService $faqService,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();

        $search = $request->string('q')->trim()->toString() ?: null;

        $faqs = $this->faqService->forUser($user, $search);

        return view('dashboard.faqs.index', compact('faqs', 'search'));
    }
}
