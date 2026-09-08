@extends('layouts.account')

@section('title', 'My Bookings — ' . config('app.name'))

@section('account-breadcrumbs')
    <x-account.breadcrumb :crumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Bookings'],
    ]" />
@endsection

@section('account-content')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-fg-strong">My Bookings</h1>
        <p class="text-fg-muted text-sm mt-1">Full history of your booked sessions. Select a booking to see its details, join link, and reschedule or cancel options.</p>
    </div>

    <livewire:frontend.student.booking-history />

@endsection
