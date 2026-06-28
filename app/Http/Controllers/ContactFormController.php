<?php

namespace App\Http\Controllers;

use App\Content\Models\ContentBlock;
use App\Enums\BlockType;
use App\Notifications\Cms\ContactFormSubmissionNotification;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class ContactFormController extends Controller
{
    /**
     * Handle contact form submission from block
     */
    public function submit(Request $request): RedirectResponse
    {
        $request->validate([
            'block_id' => ['required', 'uuid', 'exists:content_blocks,id'],
            'website' => ['nullable', 'max:0'], // Honeypot
        ]);

        /** @var ContentBlock $block */
        $block = ContentBlock::query()->findOrFail($request->string('block_id')->toString());

        if ($block->block_type !== BlockType::ContactForm) {
            throw ValidationException::withMessages([
                'block_id' => 'Invalid contact form block.',
            ]);
        }

        $content = is_array($block->content) ? $block->content : (json_decode((string) $block->content, true) ?? []);
        $fields = $content['fields'] ?? [];
        $rules = [];

        foreach ($fields as $index => $field) {
            $name = $field['name'] ?? ("field_{$index}");
            $type = $field['type'] ?? 'text';
            $required = (bool) ($field['required'] ?? false);

            $fieldRules = $required ? ['required'] : ['nullable'];
            $fieldRules[] = 'string';
            $fieldRules[] = 'max:2000';

            if ($type === 'email') {
                $fieldRules = array_values(array_filter([...$fieldRules, 'email']));
            }

            if ($type === 'phone') {
                $fieldRules[] = 'max:30';
            }

            if ($type === 'select') {
                $options = array_filter(array_map('trim', explode(',', (string) ($field['options'] ?? ''))));
                if ($options !== []) {
                    $fieldRules[] = 'in:' . implode(',', $options);
                }
            }

            $rules[$name] = $fieldRules;
        }

        $validated = $request->validate($rules);

        $payload = [
            'block_id' => $block->id,
            'page_id' => $block->blockable_id,
            'page_slug' => $block->page?->slug,
            'page_title' => $block->page?->title,
            'submitted_at' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'fields' => $validated,
            'field_labels' => collect($fields)->mapWithKeys(function (array $field, int $index): array {
                $name = $field['name'] ?? ("field_{$index}");
                $label = $field['label'] ?? $name;

                return [$name => $label];
            })->all(),
        ];

        $generalSettings = app(GeneralSettings::class);
        $mailSettings = app(MailSettings::class);
        $recipient = $generalSettings->support_email ?: config('mail.from.address');

        if (! is_string($recipient) || $recipient === '') {
            throw new RuntimeException('Contact form recipient email is not configured.');
        }

        if ($mailSettings->queue_emails) {
            Notification::route('mail', $recipient)
                ->notify(new ContactFormSubmissionNotification($payload));
        } else {
            Notification::route('mail', $recipient)
                ->notifyNow(new ContactFormSubmissionNotification($payload));
        }

        activity('contact')
            ->performedOn($block)
            ->event('contact_form_submitted')
            ->withProperties([
                'page_id' => $block->blockable_id,
                'ip' => $request->ip(),
            ])
            ->log('Contact form submitted');

        return back()->with('success', $content['success_message'] ?? 'Thank you for your message. We\'ll get back to you soon!');
    }
}
