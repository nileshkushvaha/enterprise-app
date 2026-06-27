<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\PageRenderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PagePreviewController extends Controller
{
    /**
     * Preview a page (draft, scheduled, or archived)
     * 
     * @throws AuthorizationException
     */
    public function __invoke(Request $request, Page $page, PageRenderService $renderService): Response
    {
        // Only authenticated users with permission can preview
        if (!auth()->check()) {
            abort(403, 'Unauthorized');
        }

        // Check permission: user can only preview pages they created or admin
        if (!auth()->user()->can('pages.view', $page)) {
            abort(403, 'You do not have permission to preview this page');
        }

        // Render the page using the same service
        try {
            $html = $renderService->renderPreview($page);
            $seo = $renderService->getSeoMetadata($page);
            $structuredData = $renderService->getStructuredData($page);
        } catch (\Exception $e) {
            return response("Preview error: " . $e->getMessage(), 500);
        }

        // Return preview response (this will be wrapped in a modal by Filament)
        return response()->view('admin.page-preview', [
            'page' => $page,
            'html' => $html,
            'seo' => $seo,
            'structured_data' => $structuredData,
        ]);
    }
}
