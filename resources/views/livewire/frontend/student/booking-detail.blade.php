<div>
    @if($booking)
        @php
            $isActive = ! $booking->status->isTerminal();
            $rescheduleAllowance = $this->rescheduleAllowance();
        @endphp

        @if($banner)
            <x-ui.alert type="error" class="mb-4">{{ $banner }}</x-ui.alert>
        @endif

        {{-- Summary header: the answers a student opens this page for —
             which session, when, with whom, and what state it is in. --}}
        <x-account.card class="mb-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-bold text-fg-strong">{{ $booking->type?->name ?? 'Session' }}</h2>
                        <x-ui.badge :color="$booking->status->color()">{{ $booking->status->label() }}</x-ui.badge>
                        @if($booking->payment_status !== \App\Booking\Enums\BookingPaymentStatus::NotRequired)
                            <x-ui.badge :color="$booking->payment_status->color()">{{ $booking->payment_status->label() }}</x-ui.badge>
                        @endif
                    </div>
                    <p class="mt-1.5 text-sm text-fg-muted">
                        {{ viewer_datetime_labelled($booking->starts_at) }}
                    </p>
                    <p class="mt-1 text-xs text-fg-faint">Reference {{ $booking->reference }}</p>
                </div>

                @if($booking->price !== null && $booking->payment_status !== \App\Booking\Enums\BookingPaymentStatus::NotRequired)
                    <div class="text-right">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-fg-faint">Amount</p>
                        <p class="text-lg font-bold text-fg-strong">{{ $booking->currency }} {{ number_format((float) $booking->price, 2) }}</p>
                    </div>
                @endif
            </div>

            {{-- $joinUrl comes exclusively from BookingMeetingService::studentJoinUrlFor(); this blade never reads meeting->join_url directly. --}}
            @if($booking->status->value === 'confirmed' && $joinUrl)
                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-edge pt-4">
                    <x-ui.button :href="$joinUrl" target="_blank" rel="noopener" size="sm">Join the lesson</x-ui.button>
                    @if($booking->meeting?->password)
                        <p class="text-xs text-fg-muted">Passcode: <span class="font-semibold text-fg-strong">{{ $booking->meeting->password }}</span></p>
                    @endif
                </div>
            @endif
        </x-account.card>

        <x-account.card title="Session details">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wide text-fg-faint">When</dt>
                    <dd class="mt-1 font-semibold text-fg-strong">{{ viewer_datetime_labelled($booking->starts_at) }}</dd>
                </div>
                <div>
                    {{-- TZ-4: provenance, not the viewer's clock. The "When"
                         line above already carries the viewer's own timezone
                         label; this records which timezone the booking was
                         originally made in (see Booking's class docblock). --}}
                    <dt class="text-[11px] font-bold uppercase tracking-wide text-fg-faint">Booked in</dt>
                    <dd class="mt-1 font-semibold text-fg-strong">{{ $booking->timezone }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wide text-fg-faint">Instructor</dt>
                    <dd class="mt-1 font-semibold">
                        @if($booking->instructor)
                            <a href="{{ route('instructors.show', $booking->instructor) }}" target="_blank" rel="noopener" class="text-indigo-600 underline underline-offset-2 hover:text-indigo-700 dark:text-indigo-300 hover:dark:text-indigo-200">{{ $booking->instructor->name }}</a>
                        @else
                            <span class="text-fg-strong">Teacher</span>
                        @endif
                    </dd>
                </div>
                @if(($booking->meta['subject'] ?? null) !== null)
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-fg-faint">Subject</dt>
                        <dd class="mt-1 font-semibold capitalize text-fg-strong">
                            {{ str_replace(['_', '-'], ' ', $booking->meta['subject']) }}
                            {{-- Phase 3.1: a country-aware academic booking carries its own
                                 immutable snapshot (e.g. "Class 10") — prefer it over the
                                 legacy "Grade {n}" fallback, and never reconstruct it from
                                 current EducationSystem config (the snapshot IS the historical
                                 record, even after an admin later renames the level). --}}
                            @if($booking->academicContext)
                                &middot; {{ $booking->academicContext->level_display }}
                            @elseif($booking->meta['grade'] ?? null)
                                &middot; Grade {{ $booking->meta['grade'] }}
                            @endif
                        </dd>
                    </div>
                @endif
                @if($booking->status->value === 'confirmed')
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-fg-faint">Meeting</dt>
                        @if($joinUrl)
                            <dd class="mt-1"><a href="{{ $joinUrl }}" target="_blank" rel="noopener" class="font-semibold text-indigo-600 underline underline-offset-2 dark:text-indigo-300">Join link</a></dd>
                            @if($booking->meeting?->password)
                                <dd class="mt-1 text-xs text-fg-muted">Passcode: {{ $booking->meeting->password }}</dd>
                            @endif
                        @else
                            <dd class="mt-1 text-sm text-fg-muted">Meeting link is being prepared.</dd>
                        @endif
                    </div>
                @endif
                {{-- $recordingState comes exclusively from RecordingPlaybackAccessResolver::stateFor(); the recording row is used here only as the route key, never inspected. --}}
                @if($recordingState->isVisible())
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-fg-faint">Recording</dt>
                        <dd class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <x-ui.badge :color="$recordingState->color()">{{ $recordingState->label() }}</x-ui.badge>
                            @if($recordingState === \App\Booking\Enums\RecordingPlaybackState::Available && $booking->recording)
                                <a href="{{ route('dashboard.recordings.watch', $booking->recording) }}" class="font-semibold text-indigo-600 underline underline-offset-2 dark:text-indigo-300">Watch recording</a>
                            @endif
                        </dd>
                        <dd class="mt-1 text-xs text-fg-muted">{{ $recordingState->description() }}</dd>
                    </div>
                @endif
            </dl>

            @if($booking->status->value === 'cancelled')
                <p class="mt-5 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-600 dark:text-red-300">
                    Cancelled
                    @if($booking->cancellation_reason)
                        &mdash; {{ $booking->cancellation_reason }}
                    @endif
                </p>

                @if($outcome = $this->cancellationOutcomeMessage())
                    <p class="mt-2 rounded-xl bg-surface-raised px-4 py-3 text-sm text-fg-muted">{{ $outcome }}</p>
                @endif
            @endif

            @if($booking->payment_status->value === 'paid')
                <p class="mt-5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-600 dark:text-emerald-300">Paid</p>
            @elseif($booking->payment_status->value === 'refunded')
                <p class="mt-5 rounded-xl border border-slate-500/20 bg-slate-500/10 px-4 py-3 text-sm text-fg-muted">
                    @if($this->paymentWasCreditedToWallet())
                        Payment received after this booking's slot was released — the amount was credited to your wallet.
                    @else
                        Refunded
                    @endif
                </p>
            @endif

            @if($isActive && ($booking->payment_status->value === 'pending' || $booking->payment_status->value === 'failed'))
                <div class="mt-5 rounded-xl border border-indigo-500/20 bg-indigo-500/10 px-4 py-3">
                    <p class="text-sm text-indigo-700 dark:text-indigo-200">Payment is {{ $booking->payment_status->label() }}. Complete payment to confirm this booking.</p>

                    <x-ui.button type="button" class="mt-3" size="sm" wire:click="initiatePayment" wire:loading.attr="disabled" wire:target="initiatePayment">
                        <span wire:loading.remove wire:target="initiatePayment">Pay now</span>
                        <span wire:loading wire:target="initiatePayment">Preparing payment...</span>
                    </x-ui.button>

                    @if($this->walletOption()['available'] ?? false)
                        <div class="mt-3 rounded-xl border border-edge bg-surface-raised p-3">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-fg-muted">Pay with wallet</p>
                            <p class="mt-1 text-xs text-fg-muted">Wallet balance: <span class="font-semibold text-fg-strong">{{ $this->walletOption()['balance_formatted'] }}</span></p>

                            @if($this->walletOption()['sufficient'] ?? false)
                                <x-ui.button type="button" variant="ghost" size="sm" class="mt-2" wire:click="payWithWallet" wire:loading.attr="disabled" wire:target="payWithWallet">
                                    <span wire:loading.remove wire:target="payWithWallet">Pay from wallet</span>
                                    <span wire:loading wire:target="payWithWallet">Paying...</span>
                                </x-ui.button>
                            @else
                                <p class="mt-2 text-[11px] text-amber-600 dark:text-amber-300">Your wallet balance is not sufficient to pay for this booking.</p>
                            @endif
                        </div>
                    @endif

                    @if(($paymentOrder['provider'] ?? null) === 'stripe')
                        {{-- wire:ignore: this subtree is polled by checkPaymentStatus() every few
                             seconds while confirming — Livewire must never re-morph it, or the
                             mounted Stripe Elements iframe (DOM Livewire doesn't know about) would
                             be torn down mid-confirmation. --}}
                        <div class="mt-3" wire:ignore>
                            <div id="stripe-payment-element" class="rounded-lg bg-white p-3"></div>
                            <p id="stripe-payment-errors" class="mt-2 text-xs font-semibold text-rose-600 dark:text-rose-300" role="alert"></p>
                            <x-ui.button type="button" id="stripe-confirm-button" class="mt-3 w-full justify-center" disabled>
                                Confirm card payment
                            </x-ui.button>
                        </div>
                    @endif

                    @if(($paymentOrder['provider'] ?? null) === 'fake' && app()->environment(['local', 'testing']))
                        <div class="mt-3 rounded-lg border border-amber-300/20 bg-amber-400/10 p-3">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:text-amber-200">Test mode — fake provider</p>
                            <div class="mt-2 flex gap-2">
                                <x-ui.button type="button" size="sm" wire:click="simulateFakePayment(true)" wire:loading.attr="disabled">Simulate success</x-ui.button>
                                <x-ui.button type="button" size="sm" variant="ghost" wire:click="simulateFakePayment(false)" wire:loading.attr="disabled">Simulate failure</x-ui.button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @if($isActive && $booking->hasStarted())
                <div class="mt-6 rounded-xl border border-edge bg-surface-raised px-4 py-3 text-sm text-fg-muted" data-lesson-started-notice>
                    @if($booking->hasEnded())
                        This lesson has ended. It will be marked as completed automatically, and rescheduling or cancelling is no longer available.
                    @else
                        This lesson is in progress. Rescheduling or cancelling is no longer available.
                    @endif
                </div>
            @elseif($isActive)
                <div class="mt-6 flex flex-wrap gap-3 border-t border-edge pt-5">
                    @if($rescheduleAllowance === null || $rescheduleAllowance['allowed'])
                        <x-ui.button type="button" wire:click="openReschedulePanel" size="sm">Reschedule</x-ui.button>
                    @endif
                    <x-ui.button type="button" variant="danger" wire:click="openCancelPanel" size="sm">Cancel booking</x-ui.button>
                </div>

                @if($rescheduleAllowance !== null && ! $rescheduleAllowance['allowed'])
                    <p class="mt-2 text-xs text-amber-600 dark:text-amber-300">You have reached the reschedule limit for this lesson.</p>
                @endif

                @if($reschedulePanelOpen)
                    <section class="mt-4 rounded-2xl border border-edge bg-surface-raised p-4" aria-label="Reschedule booking">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-fg-strong">Pick a new time</h3>
                                @if($rescheduleAllowance !== null)
                                    <p class="mt-0.5 text-xs text-fg-muted">
                                        {{ $rescheduleAllowance['remaining'] === 1 ? '1 reschedule remaining' : $rescheduleAllowance['remaining'].' reschedules remaining' }}
                                    </p>
                                @endif
                            </div>
                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="closeReschedulePanel">Close</x-ui.button>
                        </div>

                        <label for="reschedule-date" class="mt-3 block text-sm font-semibold text-fg">New date</label>
                        <input
                            id="reschedule-date"
                            type="date"
                            wire:model.live="rescheduleDate"
                            min="{{ now()->addDay()->toDateString() }}"
                            class="mt-1.5 rounded-xl border border-edge bg-surface-raised px-3.5 py-2.5 text-sm text-fg-strong shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-4 focus:ring-indigo-400/20"
                        >

                        <div wire:loading wire:target="rescheduleDate" class="mt-3 text-sm text-fg-muted">Loading times...</div>

                        @if(!empty($rescheduleSlots))
                            <div wire:loading.remove wire:target="rescheduleDate" class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4" role="group" aria-label="Choose a new time">
                                @foreach($rescheduleSlots as $slot)
                                    <button
                                        type="button"
                                        wire:click="selectRescheduleSlot('{{ $slot['starts_at'] }}')"
                                        aria-pressed="{{ $rescheduleSlotStartsAt === $slot['starts_at'] ? 'true' : 'false' }}"
                                        class="rounded-xl border-2 p-2 text-sm font-semibold transition {{ $rescheduleSlotStartsAt === $slot['starts_at'] ? 'border-indigo-500 bg-indigo-500/10 text-indigo-700 dark:text-indigo-200' : 'border-edge bg-surface-raised text-fg hover:border-indigo-400/40' }}"
                                    >{{ viewer_time($slot['starts_at']) }}</button>
                                @endforeach
                            </div>
                        @elseif($rescheduleDate)
                            <p wire:loading.remove wire:target="rescheduleDate" class="mt-3 text-sm text-fg-muted">No open times on that date &mdash; try another.</p>
                        @endif

                        <x-ui.button type="button" wire:click="confirmReschedule" :disabled="!$rescheduleSlotStartsAt" class="mt-4" size="sm">Confirm new time</x-ui.button>
                    </section>
                @endif

                @if($cancelPanelOpen)
                    <section class="mt-4 rounded-2xl border border-red-500/20 bg-red-500/[0.06] p-4" aria-label="Cancel booking">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-bold text-fg-strong">Cancel this booking</h3>
                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="closeCancelPanel">Close</x-ui.button>
                        </div>

                        @if($preview = $this->cancellationRefundPreview())
                            @if($preview['eligible'])
                                <p class="mt-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-600 dark:text-emerald-300">Eligible for a full wallet refund.</p>
                            @else
                                <p class="mt-3 rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-600 dark:text-amber-300">
                                    This cancellation is outside the refund window and will not be refunded.
                                    @if($preview['cutoff_at'])
                                        The refund deadline was {{ viewer_datetime_labelled($preview['cutoff_at'], 'D, M j Y \a\t H:i') }}.
                                    @endif
                                </p>
                            @endif
                            <p class="mt-2 text-xs text-fg-muted">Eligible refunds are credited to your wallet, not your original payment method.</p>
                        @endif

                        <label for="cancel-reason" class="mt-3 block text-sm font-semibold text-fg">Reason (optional)</label>
                        <textarea id="cancel-reason" rows="2" wire:model="cancelReason" maxlength="500"
                                  class="mt-1.5 block w-full rounded-xl border border-edge bg-surface-raised px-3.5 py-2.5 text-sm text-fg-strong shadow-sm focus:border-red-400 focus:outline-none focus:ring-4 focus:ring-red-400/20"></textarea>
                        <x-ui.button type="button" variant="danger" wire:click="confirmCancel" class="mt-3" size="sm">Yes, cancel this booking</x-ui.button>
                    </section>
                @endif
            @endif
        </x-account.card>
    @endif
</div>

@script
@include('livewire.frontend.booking.partials.razorpay-checkout-script')
@include('livewire.frontend.booking.partials.stripe-checkout-script')
@endscript
