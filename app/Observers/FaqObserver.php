<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FaqStatus;
use App\Models\Faq;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;

class FaqObserver
{
    public function __construct(
        private readonly AuditTrailService $auditTrail,
    ) {}

    public function created(Faq $faq): void
    {
        $this->log('faqs', 'created', 'FAQ created', $faq, [
            'question' => $faq->question,
            'category_id' => $faq->faq_category_id,
            'status' => $faq->status?->value,
            'audience' => $faq->audience,
        ]);
    }

    public function updated(Faq $faq): void
    {
        $wasPublished = $faq->isDirty('status')
            && $faq->status === FaqStatus::Published
            && $faq->getOriginal('status') !== FaqStatus::Published->value;

        if ($wasPublished) {
            $this->log('faqs', 'published', 'FAQ published', $faq, [
                'question' => $faq->question,
                'published_at' => $faq->published_at?->toDateTimeString(),
            ]);

            return;
        }

        $this->log('faqs', 'updated', 'FAQ updated', $faq, [
            'question' => $faq->question,
            'changes' => array_keys($faq->getChanges()),
        ]);
    }

    public function deleted(Faq $faq): void
    {
        $this->log('faqs', 'deleted', 'FAQ deleted', $faq, [
            'question' => $faq->question,
            'category_id' => $faq->faq_category_id,
        ]);
    }

    public function restored(Faq $faq): void
    {
        $this->log('faqs', 'restored', 'FAQ restored', $faq, [
            'question' => $faq->question,
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
