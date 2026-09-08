@extends('layouts.frontend')

@section('theme-aware', 'true')

{{-- No pre-footer marketing band while the student is booking — see
     layouts/frontend.blade.php. --}}
@section('hide-pre-footer-cta', 'true')

@section('title', 'Book a Session — '.config('app.name'))
@section('meta_description', 'Choose an instructor session, select an available time in your timezone, and review every detail before confirming your booking.')

@section('content')
    <livewire:frontend.booking.booking-wizard />
@endsection
