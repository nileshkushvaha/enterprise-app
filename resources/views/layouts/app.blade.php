<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @isset($seo)
            <title>{{ $seo['title'] }}</title>
            <meta name="description" content="{{ $seo['description'] ?? '' }}">
            <meta name="keywords" content="{{ $seo['keywords'] ?? '' }}">
            <meta name="robots" content="{{ $seo['robots'] ?? 'index, follow' }}">
            @if($seo['canonical'] ?? null)
                <link rel="canonical" href="{{ $seo['canonical'] }}">
            @endif
        @else
            <title>{{ config('app.name', 'Laravel') }}</title>
        @endisset

        @yield('meta')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-white">
            @yield('content')
        </div>
    </body>
</html>
