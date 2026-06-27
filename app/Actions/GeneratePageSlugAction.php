<?php

namespace App\Actions;

use App\Models\Page;
use Illuminate\Support\Str;

class GeneratePageSlugAction
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    public function execute(string $title, ?string $excludeId = null, string $modelClass = Page::class): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId, $modelClass)) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function slugExists(string $slug, ?string $excludeId = null, string $modelClass = Page::class): bool
    {
        $query = $modelClass::query()->where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
