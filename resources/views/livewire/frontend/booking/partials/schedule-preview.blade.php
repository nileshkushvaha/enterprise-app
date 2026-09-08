{{--
    The schedule, as the server computed it.

    Every date, status and reason here came back from
    WizardBookingService::previewSeries(); nothing on this page works a
    date out for itself. That is what keeps what the student is shown
    identical to what confirmation will do.
--}}
@php
    $meta = $previewMeta ?? [];
    $previewError = $meta['error'] ?? null;
    $conflictCount = (int) ($meta['conflicts'] ?? 0);
@endphp

<section aria-labelledby="booking-schedule-preview" class="rounded-2xl border border-edge bg-surface p-4 sm:p-5">
    @php
        $classCount = $meta['total'] ?? null;
        $isOngoing = (bool) ($meta['ongoing'] ?? false);
        $lastDate = $meta['last_date'] ?? null;
        $money = null;

        if (($pricePreview['currency'] ?? null) !== null) {
            $minorUnits = $pricePreview['minor_units'] ?? 2;
            $money = fn (float $amount): string => \App\Support\MoneyFormatter::format(
                \App\Support\MoneyFormatter::toMinor(number_format($amount, $minorUnits, '.', ''), $minorUnits),
                $pricePreview['currency'],
                $minorUnits,
            );
        }
    @endphp

    {{--
        The same schedule as one spoken sentence. The visual summary
        below is a grid of labelled values, which reads well by eye and
        badly aloud; this is what a screen reader announces when the
        schedule changes.
    --}}
    <p class="sr-only" aria-live="polite">{{ $recurrenceSummary }}</p>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <h3 id="booking-schedule-preview" class="text-lg font-black text-fg-strong">Your schedule</h3>
        <p class="rounded-full bg-indigo-500/10 px-3 py-1 text-xs font-black uppercase tracking-wide text-indigo-700 dark:text-indigo-200">
            {{ $isOngoing ? 'Ongoing' : ($classCount !== null ? $classCount.' '.\Illuminate\Support\Str::plural('class', $classCount) : 'Checking…') }}
        </p>
    </div>

    {{--
        The live summary. Everything the student has decided, in one
        place, in their own timezone — including the LAST class, which is
        the fact a class count alone never makes obvious.
    --}}
    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
        @if($cadenceLabel)
            <div class="flex justify-between gap-3 sm:block">
                <dt class="text-fg-muted sm:text-xs sm:font-bold sm:uppercase sm:tracking-wide">Days</dt>
                <dd class="text-right font-semibold text-fg-strong sm:mt-0.5 sm:text-left">{{ $cadenceLabel }}@if($perWeekLabel) <span class="font-normal text-fg-muted">· {{ $perWeekLabel }}</span>@endif</dd>
            </div>
        @endif
        @if($selectedSlotStartsAt)
            <div class="flex justify-between gap-3 sm:block">
                <dt class="text-fg-muted sm:text-xs sm:font-bold sm:uppercase sm:tracking-wide">Time</dt>
                <dd class="text-right font-semibold text-fg-strong sm:mt-0.5 sm:text-left">
                    {{ \Carbon\CarbonImmutable::parse($selectedSlotStartsAt)->timezone($timezone)->format('g:i A') }}
                    <span class="font-normal text-fg-muted">· {{ $timezone }}</span>
                </dd>
            </div>
        @endif
        @if($firstClassDate)
            <div class="flex justify-between gap-3 sm:block">
                <dt class="text-fg-muted sm:text-xs sm:font-bold sm:uppercase sm:tracking-wide">First class</dt>
                <dd class="text-right font-semibold text-fg-strong sm:mt-0.5 sm:text-left">{{ \Carbon\CarbonImmutable::parse($firstClassDate)->format('D, j M Y') }}</dd>
            </div>
        @endif
        <div class="flex justify-between gap-3 sm:block">
            <dt class="text-fg-muted sm:text-xs sm:font-bold sm:uppercase sm:tracking-wide">Last class</dt>
            <dd class="text-right font-semibold text-fg-strong sm:mt-0.5 sm:text-left">
                {{ $isOngoing ? 'No end date' : ($lastDate ? \Carbon\CarbonImmutable::parse($lastDate)->format('D, j M Y') : '—') }}
            </dd>
        </div>
    </dl>

    @if($money)
        {{--
            Three figures, kept apart on purpose: what one class costs,
            what the whole finite schedule comes to, and what is actually
            payable right now. An ongoing schedule gets no total — there
            is no last class, so any total would be invented.
        --}}
        <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 border-t border-edge pt-3 text-sm">
            <div>
                <dt class="text-xs font-bold uppercase tracking-wide text-fg-muted">Per class</dt>
                <dd class="mt-0.5 font-black text-fg-strong">{{ $pricePreview['total_formatted'] }}</dd>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-wide text-fg-muted">Total scheduled</dt>
                <dd class="mt-0.5 font-black text-fg-strong">
                    {{-- An ongoing schedule has no last class, so it has
                         no total. Saying what it DOES cost is more use
                         than saying what cannot be computed. --}}
                    {{ $isOngoing || $classCount === null
                        ? 'Ongoing · '.$pricePreview['total_formatted'].' per class'
                        : $money((float) $pricePreview['payable_amount'] * $classCount) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-wide text-fg-muted">Payable now</dt>
                <dd class="mt-0.5 font-black text-fg-strong">{{ $pricePreview['total_formatted'] }}</dd>
            </div>
        </dl>
        <p class="mt-1.5 text-xs leading-5 text-fg-faint">You pay for one class at a time; nothing is charged automatically.</p>
    @endif

    <div wire:loading.flex wire:target="loadSchedulePreview,skipOccurrence,restoreOccurrence,moveOccurrenceTo,startMovingOccurrence,previewNextPage,previewPreviousPage,setFrequency,toggleWeekday,setRepeatInterval,setEndCondition,setOccurrences,setEndDate"
         class="mt-5 min-h-24 items-center justify-center gap-3 text-sm text-fg-muted" role="status">
        <x-ui.spinner size="sm" />
        Checking your dates…
    </div>

    <div wire:loading.remove wire:target="loadSchedulePreview,skipOccurrence,restoreOccurrence,moveOccurrenceTo,startMovingOccurrence,previewNextPage,previewPreviousPage,setFrequency,toggleWeekday,setRepeatInterval,setEndCondition,setOccurrences,setEndDate">
        @if($previewError)
            <div class="mt-4 rounded-2xl border border-amber-400/50 bg-amber-500/10 px-4 py-3" role="alert">
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ $previewError }}</p>
                <button type="button" wire:click="loadSchedulePreview" class="mt-2 min-h-11 rounded-xl px-1 text-sm font-bold text-indigo-600 underline hover:text-indigo-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-300/50 dark:text-indigo-300">
                    Try again
                </button>
            </div>
        @elseif(empty($schedulePreview))
            <div class="mt-4 rounded-2xl border border-dashed border-edge-strong px-4 py-5 text-center">
                <p class="text-sm font-semibold text-fg-strong">This pattern does not produce any classes.</p>
                <p class="mt-1 text-sm text-fg-muted">Try a different repeat pattern or end date.</p>
            </div>
        @else
            @if($conflictCount > 0)
                <div class="mt-4 rounded-2xl border border-amber-400/50 bg-amber-500/10 px-4 py-3" role="alert">
                    <p class="text-sm font-black text-amber-900 dark:text-amber-200">
                        {{ $conflictCount }} {{ $conflictCount === 1 ? 'date needs' : 'dates need' }} your attention
                    </p>
                    <p class="mt-1 text-sm leading-6 text-amber-900/90 dark:text-amber-100/90">
                        We will not book a class we cannot actually hold, and we never move it to another instructor.
                        Choose another time for each one, remove it, or change the repeat pattern above.
                    </p>
                </div>
            @endif

            <ol class="mt-4 divide-y divide-edge" aria-label="Classes in this schedule">
                @foreach($schedulePreview as $occurrence)
                    @php
                        $isSkipped = in_array($occurrence['local_date'], $skippedDates, true);
                        $isMoved = array_key_exists($occurrence['local_date'], $movedOccurrences);
                    @endphp
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold {{ $isSkipped ? 'text-fg-faint line-through' : 'text-fg-strong' }}">
                                {{-- A removed date has no place in the schedule, so it has no number. --}}
                                <span class="tabular-nums text-fg-muted">{{ $occurrence['sequence'] > 0 ? $occurrence['sequence'].'.' : '—' }}</span>
                                {{ $occurrence['date_label'] }}
                                @if($occurrence['time_label'])
                                    · {{ $occurrence['time_label'] }}@if($occurrence['ends_label'])–{{ $occurrence['ends_label'] }}@endif
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs font-semibold
                                {{ $occurrence['is_conflict'] ? 'text-amber-700 dark:text-amber-300' : 'text-fg-muted' }}">
                                @if($isSkipped)
                                    Removed from this schedule
                                @else
                                    {{ $occurrence['status_label'] }}@if($isMoved) · moved @endif
                                    @if($occurrence['reason']) — {{ $occurrence['reason'] }} @endif
                                @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            @if($isSkipped)
                                <button type="button" wire:click="restoreOccurrence('{{ $occurrence['local_date'] }}')"
                                    class="min-h-11 rounded-xl px-3 text-sm font-bold text-indigo-600 hover:bg-surface-hover focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-300/50 dark:text-indigo-300">
                                    Put back<span class="sr-only"> the class on {{ $occurrence['date_label'] }}</span>
                                </button>
                            @else
                                <button type="button" wire:click="startMovingOccurrence('{{ $occurrence['local_date'] }}')"
                                    class="min-h-11 rounded-xl px-3 text-sm font-bold text-indigo-600 hover:bg-surface-hover focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-300/50 dark:text-indigo-300">
                                    Change time<span class="sr-only"> for {{ $occurrence['date_label'] }}</span>
                                </button>
                                <button type="button" wire:click="skipOccurrence('{{ $occurrence['local_date'] }}')"
                                    class="min-h-11 rounded-xl px-3 text-sm font-bold text-fg-muted hover:bg-surface-hover focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-300/50">
                                    Remove<span class="sr-only"> the class on {{ $occurrence['date_label'] }}</span>
                                </button>
                            @endif
                        </div>

                        @if($movingDate === $occurrence['local_date'])
                            <div class="w-full rounded-2xl border border-edge bg-surface-raised p-3" role="group" aria-label="Other times on {{ $occurrence['date_label'] }}">
                                @if(empty($moveSlots))
                                    <p class="text-sm text-fg-muted">Your instructor has no other free times on this date. You can remove this class instead, or change the repeat pattern.</p>
                                @else
                                    <p class="text-xs font-bold uppercase tracking-wide text-fg-muted">Other times with the same instructor</p>
                                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        @foreach($moveSlots as $slot)
                                            <button type="button" wire:click="moveOccurrenceTo('{{ $slot['local_time'] }}')"
                                                aria-label="Move to {{ $slot['label'] }} to {{ $slot['ends_label'] }}"
                                                class="min-h-11 rounded-xl border-2 border-edge bg-surface px-2 text-sm font-bold text-fg transition hover:border-indigo-300 focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-300/50">
                                                {{ $slot['label'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                                <button type="button" wire:click="cancelMove" class="mt-2 min-h-11 rounded-xl px-1 text-sm font-bold text-fg-muted hover:underline focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-300/50">Cancel</button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>

            @if($previewPage > 1 || ($meta['has_more'] ?? false))
                <div class="mt-3 flex items-center justify-between gap-3">
                    <button type="button" wire:click="previewPreviousPage" @disabled($previewPage <= 1)
                        class="min-h-11 rounded-xl border border-edge px-3 text-sm font-bold text-fg transition hover:bg-surface-hover focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-300/50 disabled:cursor-not-allowed disabled:opacity-40">
                        Earlier classes
                    </button>
                    <span class="text-xs font-semibold text-fg-muted" aria-live="polite">Page {{ $previewPage }}</span>
                    <button type="button" wire:click="previewNextPage" @disabled(! ($meta['has_more'] ?? false))
                        class="min-h-11 rounded-xl border border-edge px-3 text-sm font-bold text-fg transition hover:bg-surface-hover focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-300/50 disabled:cursor-not-allowed disabled:opacity-40">
                        Later classes
                    </button>
                </div>
            @endif

            <div class="mt-4 space-y-1.5 text-xs leading-5 text-fg-muted">
                @if($horizonExplainer)<p>{{ $horizonExplainer }}</p>@endif
                @if($skipPolicyExplainer)<p>{{ $skipPolicyExplainer }}</p>@endif
            </div>
        @endif
    </div>
</section>
