@extends('layouts.app')

@section('meta')
    <meta name="description" content="{{ $seo['description'] ?? '' }}">
    <meta name="keywords" content="{{ $seo['keywords'] ?? '' }}">
    <meta name="robots" content="{{ $seo['robots'] ?? 'index, follow' }}">
    @if($seo['canonical'])
        <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endif

    <!-- Open Graph Tags -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seo['og_title'] ?? '' }}">
    <meta property="og:description" content="{{ $seo['og_description'] ?? '' }}">
    <meta property="og:url" content="{{ $seo['og_url'] ?? '' }}">
    @if($seo['og_image'])
        <meta property="og:image" content="{{ $seo['og_image'] }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
    @endif

    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['og_title'] ?? '' }}">
    <meta name="twitter:description" content="{{ $seo['og_description'] ?? '' }}">
    @if($seo['og_image'])
        <meta name="twitter:image" content="{{ $seo['og_image'] }}">
    @endif

    <!-- Structured Data -->
    @if($structured_data)
        <script type="application/ld+json">
            {!! json_encode($structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endif
@endsection

@section('content')
    {!! $html !!}
@endsection
