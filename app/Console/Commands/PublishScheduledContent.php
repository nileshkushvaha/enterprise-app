<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use App\Models\Page;
use App\Models\Post;
use App\Services\CmsCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublishScheduledContent extends Command
{
    protected $signature = 'cms:publish-scheduled
                            {--dry-run : Show what would be published without actually publishing}';

    protected $description = 'Publish Pages and Posts whose scheduled publish time has arrived';

    public function handle(CmsCacheService $cache): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('Dry-run mode — no changes will be saved.');
        }

        // Single UUID groups every activity entry produced in this scheduler run so
        // operators can filter "show me everything published in batch X" via batch_uuid.
        $batchUuid = (string) Str::uuid();

        $publishedPages = $this->publishPages($dryRun, $batchUuid);
        $publishedPosts = $this->publishPosts($dryRun, $batchUuid);
        $total = $publishedPages + $publishedPosts;

        if ($total > 0 && ! $dryRun) {
            $cache->bumpDiscoveryVersion();
        }

        $this->components->info(
            $dryRun
                ? "Would publish {$publishedPages} page(s) and {$publishedPosts} post(s)."
                : "Published {$publishedPages} page(s) and {$publishedPosts} post(s)."
        );

        return self::SUCCESS;
    }

    private function publishPages(bool $dryRun, string $batchUuid): int
    {
        // Page::scopeScheduled() already enforces published_at <= now()
        $pages = Page::scheduled()->get();

        if ($pages->isEmpty()) {
            return 0;
        }

        $published = 0;

        foreach ($pages as $page) {
            if ($dryRun) {
                $this->components->twoColumnDetail(
                    "Page: {$page->title}",
                    "scheduled → published (publish_at: {$page->published_at})"
                );
                $published++;

                continue;
            }

            try {
                DB::transaction(function () use ($page, $batchUuid): void {
                    $page->update([
                        'status' => PageStatus::Published,
                        'visibility' => PageVisibility::Public,
                    ]);

                    $log = activity('cms')
                        ->performedOn($page)
                        ->event('auto_published')
                        ->withProperties(['scheduled_at' => $page->published_at])
                        ->log("Page auto-published by scheduler: {$page->title}");

                    if ($log) {
                        $log->forceFill(['batch_uuid' => $batchUuid])->saveQuietly();
                    }
                });

                $this->components->twoColumnDetail("Page: {$page->title}", 'published ✓');
                $published++;
            } catch (\Throwable $e) {
                $this->components->error("Failed to publish page [{$page->id}]: {$e->getMessage()}");
            }
        }

        return $published;
    }

    private function publishPosts(bool $dryRun, string $batchUuid): int
    {
        // Post::scopeScheduled() enforces published_at <= now()
        $posts = Post::scheduled()->get();

        if ($posts->isEmpty()) {
            return 0;
        }

        $published = 0;

        foreach ($posts as $post) {
            if ($dryRun) {
                $this->components->twoColumnDetail(
                    "Post: {$post->title}",
                    "scheduled → published (publish_at: {$post->published_at})"
                );
                $published++;

                continue;
            }

            try {
                DB::transaction(function () use ($post, $batchUuid): void {
                    $post->update([
                        'status' => PageStatus::Published,
                        'visibility' => PageVisibility::Public,
                    ]);

                    $log = activity('cms')
                        ->performedOn($post)
                        ->event('auto_published')
                        ->withProperties(['scheduled_at' => $post->published_at])
                        ->log("Post auto-published by scheduler: {$post->title}");

                    if ($log) {
                        $log->forceFill(['batch_uuid' => $batchUuid])->saveQuietly();
                    }
                });

                $this->components->twoColumnDetail("Post: {$post->title}", 'published ✓');
                $published++;
            } catch (\Throwable $e) {
                $this->components->error("Failed to publish post [{$post->id}]: {$e->getMessage()}");
            }
        }

        return $published;
    }
}
