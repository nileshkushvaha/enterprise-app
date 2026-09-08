@extends('layouts.account')

@section('title', 'Booking ' . $booking->reference . ' — ' . config('app.name'))

@section('account-breadcrumbs')
    <x-account.breadcrumb :crumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'My Bookings', 'url' => $backUrl],
        ['label' => $booking->reference],
    ]" />
@endsection

@section('account-content')

    <div class="mb-6">
        {{-- Explicit back control: the breadcrumb above is for orientation,
             this is the tap target on mobile, where the breadcrumb is small. --}}
        <a href="{{ $backUrl }}"
           class="mb-3 inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-fg-muted transition hover:text-fg-strong focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
            </svg>
            Back to My Bookings
        </a>

        <h1 class="text-xl font-bold text-fg-strong">Booking details</h1>
        <p class="mt-1 text-sm text-fg-muted">Reference {{ $booking->reference }}</p>
    </div>

    <livewire:frontend.student.booking-detail :booking-id="$booking->id" />

    <div class="mt-6">
        <x-ui.button variant="secondary" size="sm" :href="$backUrl">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
            </svg>
            Back to My Bookings
        </x-ui.button>
    </div>

@endsection
