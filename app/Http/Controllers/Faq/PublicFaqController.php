<?php

declare(strict_types=1);

namespace App\Http\Controllers\Faq;

use App\Http\Controllers\Controller;
use App\Services\Faq\FaqService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PublicFaqController extends Controller
{
    public function __construct(
        private readonly FaqService $faqService,
    ) {}

    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString() ?: null;
        $categoryId = $request->filled('category') ? $request->input('category') : null;

        $categories = $this->faqService->categories();
        $faqs = $this->faqService->publicFaqs($search, $categoryId);
        $featured = ($search === null && $categoryId === null)
            ? $this->faqService->featured(['public'], 5)
            : collect();

        return view('faqs.index', compact('categories', 'faqs', 'featured', 'search', 'categoryId'));
    }
}
