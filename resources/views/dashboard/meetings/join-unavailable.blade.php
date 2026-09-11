@extends('layouts.account')

@section('title', 'Join lesson — ' . config('app.name'))

@section('account-breadcrumbs')
    <x-account.breadcrumb :crumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Join lesson'],
    ]" />
@endsection

@section('account-content')
@php
    use App\Booking\Enums\MeetingJoinAvailability;

    [$title, $message] = match (true) {
        ! $isParticipant => ['This lesson is not yours to join', 'Only the student and the instructor of a lesson can join it from here.'],
        $availability === MeetingJoinAvailability::TooEarly => ['The lesson has not opened yet', 'The meeting opens shortly before the scheduled start. Come back a few minutes before the lesson.'],
        $availability === MeetingJoinAvailability::NotReady => ['The meeting link is being prepared', 'The meeting for this lesson is not ready yet. Refresh this page in a moment.'],
        default => ['This lesson cannot be joined right now', 'The lesson has ended, was cancelled, or joining is not available for your account.'],
    };
@endphp

<div class="mx-auto max-w-lg rounded-2xl border border-edge bg-surface p-6" role="status" aria-live="polite">
    <p class="text-[11px] font-black uppercase tracking-[0.14em] text-fg-muted">{{ $booking->type?->name ?? 'Lesson' }} · {{ $booking->reference }}</p>
    <h1 class="mt-2 text-xl font-bold text-fg-strong">{{ $title }}</h1>
    <p class="mt-2 text-sm leading-6 text-fg-muted">{{ $message }}</p>
    @if($isParticipant && $booking->starts_at)
        <p class="mt-3 text-sm text-fg-muted">Scheduled for {{ $booking->starts_at->timezone(auth()->user()?->profile?->timezone ?? config('app.timezone'))->format('D, M j Y \a\t H:i') }}.</p>
    @endif
    <div class="mt-5 flex flex-wrap gap-3">
        <x-ui.button :href="route('dashboard')" size="sm" variant="secondary">Back to dashboard</x-ui.button>
    </div>
</div>
@endsection
