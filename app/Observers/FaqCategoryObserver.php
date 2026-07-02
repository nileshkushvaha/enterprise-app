<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FaqCategory;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;

class FaqCategoryObserver
{
    public function __construct(
        private readonly AuditTrailService $auditTrail,
    ) {}

    public function created(FaqCategory $category): void
    {
        $this->log('faq_categories', 'created', 'FAQ category created', $category, [
            'name' => $category->name,
        ]);
    }

    public function updated(FaqCategory $category): void
    {
        $this->log('faq_categories', 'updated', 'FAQ category updated', $category, [
            'name' => $category->name,
            'changes' => array_keys($category->getChanges()),
        ]);
    }

    public function deleted(FaqCategory $category): void
    {
        $this->log('faq_categories', 'deleted', 'FAQ category deleted', $category, [
            'name' => $category->name,
        ]);
    }

    public function restored(FaqCategory $category): void
    {
        $this->log('faq_categories', 'restored', 'FAQ category restored', $category, [
            'name' => $category->name,
        ]);
    }

    private function log(string $logName, string $event, string $description, Model $subject, array $properties = []): void
    {
        /** @var User|null $causer */
        $causer = auth()->user();

        if ($causer instanceof User) {
            $this->auditTrail->logUser($causer, $logName, $event, $description, $subject, $properties);
        } else {
            $this->auditTrail->logSystem($logName, $event, $description, $subject, $properties);
        }
    }
}
