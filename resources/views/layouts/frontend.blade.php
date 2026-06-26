<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name') . ' — Learn Without Limits')</title>
    <meta name="description" content="@yield('meta_description', 'Connect with 200+ verified expert tutors for personalized 1-on-1 online learning. Master any subject, ace any exam, at your own pace.')">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Alpine.js + Collapse plugin --}}
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>

    @stack('head')
</head>
<body class="antialiased bg-white text-slate-900">

    @yield('content')

    @stack('scripts')
</body>
</html>
