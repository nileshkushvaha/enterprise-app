<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Post;
use App\Services\CmsCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

        $publishedPages = $this->publishPages($dryRun);
        $publishedPosts = $this->publishPosts($dryRun);
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

    private function publishPages(bool $dryRun): int
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
                DB::transaction(function () use ($page): void {
                    $page->update([
                        'status'     => \App\Enums\PageStatus::Published,
                        'visibility' => \App\Enums\PageVisibility::Public,
                    ]);

                    activity()
                        ->performedOn($page)
                        ->event('auto_published')
                        ->withProperties(['scheduled_at' => $page->published_at])
                        ->log("Page auto-published by scheduler: {$page->title}");
                });

                $this->components->twoColumnDetail("Page: {$page->title}", 'published ✓');
                $published++;
            } catch (\Throwable $e) {
                $this->components->error("Failed to publish page [{$page->id}]: {$e->getMessage()}");
            }
        }

        return $published;
    }

    private function publishPosts(bool $dryRun): int
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
                DB::transaction(function () use ($post): void {
                    $post->update([
                        'status'     => \App\Enums\PageStatus::Published,
                        'visibility' => \App\Enums\PageVisibility::Public,
                    ]);

                    activity()
                        ->performedOn($post)
                        ->event('auto_published')
                        ->withProperties(['scheduled_at' => $post->published_at])
                        ->log("Post auto-published by scheduler: {$post->title}");
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
