<?php

namespace App\Services;

use App\Content\Rendering\ContentRenderer;

/**
 * Backward-compatible stub — all logic now lives in ContentRenderer.
 *
 * Existing callsites (observers, controllers, tests) that resolve
 * PageRenderService from the container keep working unchanged because
 * this class inherits every public method from ContentRenderer.
 */
class PageRenderService extends ContentRenderer
{
}
