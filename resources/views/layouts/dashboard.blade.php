@extends('layouts.frontend')

@section('content')
<div class="min-h-screen bg-[#05080F]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex gap-6">

            {{-- ── Left Sidebar ─────────────────────────────────────────── --}}
            <x-dashboard.sidebar :menu="$frontendMenu ?? []" />

            {{-- ── Main Content ─────────────────────────────────────────── --}}
            <div class="flex-1 min-w-0">
                @yield('dashboard-content')
            </div>

        </div>
    </div>
</div>
@endsection
