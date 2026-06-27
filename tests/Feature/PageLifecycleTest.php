<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private PageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PageService::class);
    }

    // ── Publish ───────────────────────────────────────────────────────────

    public function test_publish_sets_status_and_visibility(): void
    {
        $page = Page::factory()->create(['status' => 'draft', 'visibility' => 'private']);

        $result = $this->service->publishPage($page);

        $this->assertTrue($result);
        $page->refresh();
        $this->assertSame(PageStatus::Published, $page->status);
        $this->assertSame(PageVisibility::Public, $page->visibility);
        $this->assertNotNull($page->published_at);
    }

    public function test_published_page_is_accessible_publicly(): void
    {
        $page = Page::factory()->create([
            'slug'       => 'live-page',
            'status'     => 'published',
            'visibility' => 'public',
        ]);

        $this->get(route('page.show', $page->slug))->assertOk();
    }

    // ── Unpublish ─────────────────────────────────────────────────────────

    public function test_unpublish_reverts_to_draft_private(): void
    {
        $page = Page::factory()->create(['status' => 'published', 'visibility' => 'public']);

        $this->service->unpublishPage($page);

        $page->refresh();
        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertSame(PageVisibility::Private, $page->visibility);
    }

    public function test_unpublished_page_returns_404(): void
    {
        $page = Page::factory()->create(['slug' => 'now-private', 'status' => 'draft', 'visibility' => 'private']);

        $this->get(route('page.show', $page->slug))->assertNotFound();
    }

    // ── Archive ───────────────────────────────────────────────────────────

    public function test_archive_sets_archived_status(): void
    {
        $page = Page::factory()->create(['status' => 'published']);

        $this->service->archivePage($page);

        $this->assertSame(PageStatus::Archived, $page->fresh()->status);
    }

    public function test_archived_page_is_not_publicly_accessible(): void
    {
        $page = Page::factory()->create([
            'slug'   => 'old-page',
            'status' => 'archived',
        ]);

        $this->get(route('page.show', $page->slug))->assertNotFound();
    }

    // ── Soft delete and restore ───────────────────────────────────────────

    public function test_soft_delete_hides_page_from_default_queries(): void
    {
        $page = Page::factory()->create();
        $id   = $page->id;

        $page->delete();

        $this->assertNull(Page::find($id));
        $this->assertNotNull(Page::withTrashed()->find($id));
        $this->assertSoftDeleted('pages', ['id' => $id]);
    }

    public function test_soft_deleted_page_returns_404(): void
    {
        $page = Page::factory()->create([
            'slug'       => 'deleted-page',
            'status'     => 'published',
            'visibility' => 'public',
        ]);
        $page->delete();

        $this->get(route('page.show', $page->slug))->assertNotFound();
    }

    public function test_restored_page_is_visible_again(): void
    {
        $page = Page::factory()->create(['status' => 'draft']);
        $id   = $page->id;
        $page->delete();

        Page::withTrashed()->find($id)->restore();

        $this->assertNotNull(Page::find($id));
    }

    // ── Scheduled visibility ──────────────────────────────────────────────

    public function test_scheduled_page_with_future_date_returns_404(): void
    {
        $page = Page::factory()->create([
            'slug'         => 'coming-soon',
            'status'       => 'scheduled',
            'published_at' => now()->addDays(7),
        ]);

        $this->get(route('page.show', $page->slug))->assertNotFound();
    }

    // ── Slug uniqueness ───────────────────────────────────────────────────

    public function test_page_slug_must_be_unique(): void
    {
        Page::factory()->create(['slug' => 'my-page']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Page::factory()->create(['slug' => 'my-page']);
    }

    // ── scopePublished ────────────────────────────────────────────────────

    public function test_published_scope_excludes_draft(): void
    {
        Page::factory()->create(['status' => 'draft', 'visibility' => 'private']);

        $this->assertCount(0, Page::published()->get());
    }

    public function test_published_scope_excludes_private_published(): void
    {
        Page::factory()->create(['status' => 'published', 'visibility' => 'private']);

        $this->assertCount(0, Page::published()->get());
    }

    public function test_published_scope_includes_public_published(): void
    {
        $page = Page::factory()->create([
            'status'     => 'published',
            'visibility' => 'public',
        ]);

        $results = Page::published()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($page));
    }

    // ── scopeScheduled ────────────────────────────────────────────────────

    public function test_scheduled_scope_only_matches_past_published_at(): void
    {
        $due = Page::factory()->create(['status' => 'scheduled', 'published_at' => now()->subMinute()]);
        Page::factory()->create(['status' => 'scheduled', 'published_at' => now()->addHour()]);

        $results = Page::scheduled()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($due));
    }
}
