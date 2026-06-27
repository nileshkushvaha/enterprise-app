<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $seo['title'] ?? ($site['app_name'] ?? config('app.name')) }}</title>
    @if($site['favicon'] ?? false)
        <link rel="icon" href="{{ $site['favicon'] }}">
    @endif
    @if(isset($seo))
        <meta name="description" content="{{ $seo['description'] ?? '' }}">
        <meta name="keywords" content="{{ $seo['keywords'] ?? '' }}">
        <meta name="robots" content="{{ $seo['robots'] ?? 'index, follow' }}">
        @if($seo['canonical'] ?? false)
            <link rel="canonical" href="{{ $seo['canonical'] }}">
        @endif
        <meta property="og:type" content="{{ $seo['og_type'] ?? 'website' }}">
        <meta property="og:title" content="{{ $seo['og_title'] ?? ($seo['title'] ?? '') }}">
        <meta property="og:description" content="{{ $seo['og_description'] ?? ($seo['description'] ?? '') }}">
        <meta property="og:url" content="{{ $seo['og_url'] ?? ($seo['canonical'] ?? '') }}">
        @if($seo['og_image'] ?? false)
            <meta property="og:image" content="{{ $seo['og_image'] }}">
        @endif
        <meta name="twitter:card" content="{{ $seo['twitter_card'] ?? 'summary_large_image' }}">
        <meta name="twitter:title" content="{{ $seo['og_title'] ?? ($seo['title'] ?? '') }}">
        <meta name="twitter:description" content="{{ $seo['og_description'] ?? ($seo['description'] ?? '') }}">
        @if($seo['og_image'] ?? false)
            <meta name="twitter:image" content="{{ $seo['og_image'] }}">
        @endif
    @endif
    @if(!empty($structured_data))
        <script type="application/ld+json">
            {!! json_encode($structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="{{ route('home') }}" class="navbar-brand">
                @if($site['logo'] ?? false)
                    <img src="{{ $site['logo'] }}" alt="{{ $site['app_name'] ?? config('app.name') }}" class="h-8 w-auto">
                @else
                    {{ $site['app_name'] ?? config('app.name') }}
                @endif
            </a>
            <div class="navbar-menu">
                <a href="{{ route('home') }}" class="nav-link">Home</a>
                <a href="{{ route('page.show', 'about') }}" class="nav-link">About</a>
                <a href="{{ route('page.show', 'contact') }}" class="nav-link">Contact</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        {!! $content ?? '' !!}
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            @if($site['footer_text'] ?? false)
                <p>{{ $site['footer_text'] }}</p>
            @else
                <p>{{ $site['footer_copyright'] ?? ('© ' . date('Y') . ' ' . ($site['app_name'] ?? config('app.name')) . '. All rights reserved.') }}</p>
            @endif
        </div>
    </footer>
</body>
</html>
