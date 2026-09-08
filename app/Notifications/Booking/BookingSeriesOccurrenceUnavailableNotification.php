<?php

declare(strict_types=1);

namespace App\Notifications\Booking;

use App\Booking\Enums\BookingStatus;
use App\Models\BookingSeries;
use App\Notifications\Booking\Concerns\RoutesBookingChannels;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * "One of your repeating classes could not be booked."
 *
 * Tells the student the specific date and time, in THEIR timezone, why
 * it could not be booked, and where to fix it. The alternative — leaving
 * it in the series view for them to discover — means the first they
 * learn of a missing class is when it does not happen.
 *
 * Timezone comes from the RECIPIENT (FormatsRecipientLocalTime via
 * RoutesBookingChannels), never from the series' own scheduling
 * calendar: this is queued code with no viewer, and the series' clock is
 * the instructor's, not the reader's.
 */
final class BookingSeriesOccurrenceUnavailableNotification extends BookingNotification
{
    use Queueable, RoutesBookingChannels, SerializesModels;

    public function __construct(
        public readonly BookingSeries $series,
        public readonly string $localDate,
        public readonly ?CarbonImmutable $startsAt,
        public readonly ?string $reason = null,
    ) {
        $this->onQueue('notifications');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->configureMailMessage(new MailMessage)
            ->subject('A class in your repeating schedule could not be booked')
            ->line(sprintf('We could not book your %s on %s.', $this->subjectLabel(), $this->when($notifiable)));

        if ($this->reason !== null) {
            $message->line($this->reason);
        }

        return $message
            ->line($this->consequence())
            ->action('Choose another time', $this->resolveUrl())
            ->line('The rest of your schedule is unaffected.');
    }

    protected function plainText(object $notifiable): string
    {
        return trim(sprintf(
            'We could not book your %s on %s. %s %s %s',
            $this->subjectLabel(),
            $this->when($notifiable),
            (string) $this->reason,
            $this->consequence(),
            $this->resolveUrl(),
        ));
    }

    /**
     * The date and time as the STUDENT reads it. A reading daylight
     * saving deleted has no instant, so the intended local date is shown
     * rather than a time that never existed.
     */
    private function when(object $notifiable): string
    {
        if ($this->startsAt === null) {
            return CarbonImmutable::parse($this->localDate)->format('D, M j Y');
        }

        $timezone = $this->recipientTimezone($notifiable);

        return sprintf(
            '%s (%s)',
            $this->startsAt->timezone($timezone)->format('D, M j Y \a\t H:i'),
            $timezone,
        );
    }

    /**
     * What this costs, stated honestly — and it differs by how the
     * schedule ends, so it is read off the series rather than guessed.
     */
    private function consequence(): string
    {
        return $this->series->isOngoing() || $this->series->occurrence_count !== null
            ? 'Your schedule runs one date further so you still get the number of classes you asked for.'
            : 'Your schedule still ends on the date you chose, so this makes it one class shorter.';
    }

    private function subjectLabel(): string
    {
        $subject = $this->series->meta['subject'] ?? null;

        return is_string($subject) && $subject !== ''
            ? ucfirst(str_replace(['_', '-'], ' ', $subject)).' class'
            : 'class';
    }

    /**
     * The repeating-schedule panel, which lives on any of the series'
     * own bookings. Falls back to the list when none is left to open.
     */
    private function resolveUrl(): string
    {
        $booking = $this->series->bookings()
            ->whereNot('status', BookingStatus::Cancelled)
            ->orderByDesc('starts_at')
            ->first();

        return $booking !== null
            ? route('dashboard.my-bookings.show', $booking->id)
            : route('dashboard.my-bookings');
    }
}
