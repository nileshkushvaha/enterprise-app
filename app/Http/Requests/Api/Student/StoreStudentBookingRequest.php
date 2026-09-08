<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Student;

use App\Booking\DTOs\RecurrencePatternData;
use App\Booking\Enums\RecurrenceFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStudentBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::exists('booking_types', 'key')->where('is_active', true)],
            'teacher_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'starts_at' => ['required', 'date', 'after:now'],
            'timezone' => ['sometimes', 'timezone:all'],
            'subject' => ['nullable', 'string', 'max:100', 'required_with:grade'],
            'grade' => ['nullable', 'integer', 'between:1,12', 'required_with:subject'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Recurrence (optional) — required_if_accepted:recurring means a
            // recurring request without an explicit frequency is rejected
            // rather than silently defaulting to weekly.
            'recurring' => ['sometimes', 'boolean'],
            //
            // `occurrences` is no longer capped at twelve. What remains is
            // an input-sanity bound, not a product limit: a schedule is
            // stored as a rule and reserved inside the confirmation
            // horizon, so a long series costs no more work up front than a
            // short one. The bound here only rejects values that cannot be
            // a real request.
            'occurrences' => ['required_if_accepted:recurring', 'integer', 'min:2', 'max:520'],
            'frequency' => ['required_if_accepted:recurring', Rule::enum(RecurrenceFrequency::class)],
            'interval' => ['sometimes', 'integer', 'between:1,'.RecurrencePatternData::MAX_INTERVAL],
        ];
    }
}
