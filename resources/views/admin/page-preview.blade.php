<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: {{ $page->title }}</title>
    
    <!-- SEO Meta Tags -->
    @if($seo)
        <meta name="description" content="{{ $seo['description'] ?? '' }}">
        <meta name="keywords" content="{{ $seo['keywords'] ?? '' }}">
        <meta name="robots" content="{{ $seo['robots'] ?? 'noindex, nofollow' }}">
        @if($seo['canonical'] ?? false)
            <link rel="canonical" href="{{ $seo['canonical'] }}">
        @endif
    @endif
    
    <!-- Open Graph -->
    @if($seo)
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $seo['og_title'] ?? '' }}">
        <meta property="og:description" content="{{ $seo['og_description'] ?? '' }}">
        <meta property="og:url" content="{{ $seo['og_url'] ?? '' }}">
        @if($seo['og_image'] ?? false)
            <meta property="og:image" content="{{ $seo['og_image'] }}">
        @endif
    @endif
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body {
            background: #f5f5f5;
        }
        .preview-container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            border: 2px solid #dc2626;
            border-radius: 8px;
            overflow: hidden;
        }
        .preview-banner {
            background: #dc2626;
            color: white;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .preview-banner h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        .preview-banner .badge {
            background: rgba(255,255,255,0.3);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .preview-content {
            padding: 0;
        }
        .preview-frame {
            width: 100%;
            border: none;
            min-height: 600px;
        }
        .preview-info {
            background: #fafafa;
            padding: 16px 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #666;
        }
        .info-row {
            margin: 8px 0;
            display: flex;
            gap: 20px;
        }
        .info-label {
            font-weight: 600;
            color: #333;
            min-width: 120px;
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <!-- Preview Banner -->
        <div class="preview-banner">
            <h2>🔍 {{ isset($post) && $post ? 'Post' : 'Page' }} Preview</h2>
            <span class="badge">
                @if($page->status->value === 'draft')
                    📝 Draft
                @elseif($page->status->value === 'scheduled')
                    ⏱️ Scheduled
                @elseif($page->status->value === 'archived')
                    📦 Archived
                @else
                    ✓ Published
                @endif
            </span>
        </div>
        
        <!-- Preview Content -->
        <div class="preview-content">
            <iframe class="preview-frame" srcdoc="{!! addslashes(str_replace('"', '&quot;', $html)) !!}"></iframe>
        </div>
        
        <!-- Preview Info -->
        <div class="preview-info">
            <h3 style="margin-top: 0; margin-bottom: 12px; font-size: 14px;">{{ isset($post) && $post ? 'Post' : 'Page' }} Information</h3>
            
            <div class="info-row">
                <div class="info-label">Title:</div>
                <div>{{ $page->title }}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Slug:</div>
                <div>{{ $page->slug }}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div>
                    <span style="
                        padding: 2px 8px;
                        border-radius: 4px;
                        font-size: 12px;
                        font-weight: 600;
                        @if($page->status->value === 'draft')
                            background: #fef3c7;
                            color: #92400e;
                        @elseif($page->status->value === 'scheduled')
                            background: #dbeafe;
                            color: #1e40af;
                        @elseif($page->status->value === 'archived')
                            background: #f3f4f6;
                            color: #4b5563;
                        @else
                            background: #dcfce7;
                            color: #166534;
                        @endif
                    ">
                        {{ ucfirst($page->status->value) }}
                    </span>
                </div>
            </div>
            
            @if($seo)
                <div class="info-row">
                    <div class="info-label">Meta Title:</div>
                    <div>{{ $seo['title'] }}</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Meta Description:</div>
                    <div style="max-width: 500px;">{{ $seo['description'] }}</div>
                </div>
            @endif
            
            @if(isset($page->layout))
                <div class="info-row">
                    <div class="info-label">Layout:</div>
                    <div>{{ ucfirst($page->layout) }}</div>
                </div>
            @endif
            
            <div class="info-row">
                <div class="info-label">Visibility:</div>
                <div>{{ ucfirst($page->visibility->value) }}</div>
            </div>
            
            @if($page->published_at)
                <div class="info-row">
                    <div class="info-label">Published:</div>
                    <div>{{ $page->published_at->format('M d, Y H:i') }}</div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
