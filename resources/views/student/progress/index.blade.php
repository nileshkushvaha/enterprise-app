@extends('layouts.account')

@section('title', 'Learning Progress — ' . config('app.name'))

@section('account-breadcrumbs')
    <x-account.breadcrumb :crumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'My Progress'],
    ]" />
@endsection

@section('account-content')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-white">My Progress</h1>
        <p class="text-slate-500 text-sm mt-1">Track your course completion, lessons, and quiz results.</p>
    </div>

    <x-account.card title="Learning Progress">
        <x-student.coming-soon
            icon="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
            title="Progress Tracking Coming Soon"
            message="Your lesson completion, quiz scores, and time spent will appear here once you start learning." />
    </x-account.card>

@endsection
