{{-- Contact Info Block — card grid with icon, value, label --}}
@php
    $iconSvgs = [
        'phone' => '<svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>',
        'email' => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>',
        'location' => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>',
        'clock'    => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'globe'    => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>',
        'chat'     => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>',
    ];

    $cardColors = [
        'phone'    => ['bg' => '#EFF6FF', 'icon_bg' => '#DBEAFE', 'icon_color' => '#2563EB'],
        'email'    => ['bg' => '#FFF7ED', 'icon_bg' => '#FFEDD5', 'icon_color' => '#EA580C'],
        'location' => ['bg' => '#F0FDF4', 'icon_bg' => '#DCFCE7', 'icon_color' => '#16A34A'],
        'clock'    => ['bg' => '#FDF4FF', 'icon_bg' => '#F3E8FF', 'icon_color' => '#9333EA'],
        'globe'    => ['bg' => '#F0F9FF', 'icon_bg' => '#E0F2FE', 'icon_color' => '#0284C7'],
        'chat'     => ['bg' => '#FFF1F2', 'icon_bg' => '#FFE4E6', 'icon_color' => '#E11D48'],
    ];

    $items = $items ?? [];
    $cols  = count($items);
    $gridClass = match(true) {
        $cols <= 1 => 'grid-cols-1 max-w-sm mx-auto',
        $cols === 2 => 'grid-cols-1 sm:grid-cols-2',
        $cols >= 4  => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
        default     => 'grid-cols-1 sm:grid-cols-3',
    };
@endphp

<section class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Heading --}}
        @if(($eyebrow ?? false) || ($title ?? false) || ($description ?? false))
        <div class="text-center mb-12">
            @if($eyebrow ?? false)
            <div class="inline-flex items-center gap-2 text-sm font-semibold mb-3" style="color: #EA580C">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
                </svg>
                {{ $eyebrow }}
            </div>
            @endif
            @if($title ?? false)
            <h2 class="text-4xl sm:text-5xl font-extrabold text-slate-900 tracking-tight">{{ $title }}</h2>
            @endif
            @if($description ?? false)
            <p class="mt-4 text-lg text-slate-500 max-w-3xl mx-auto leading-relaxed">{{ $description }}</p>
            @endif
        </div>
        @endif

        {{-- Cards grid --}}
        @if(!empty($items))
        <div class="grid {{ $gridClass }} gap-6">
            @foreach($items as $item)
            @php
                $icon      = $item['icon'] ?? 'phone';
                $colors    = $cardColors[$icon] ?? $cardColors['phone'];
                $svg       = $iconSvgs[$icon] ?? $iconSvgs['phone'];
                $value     = $item['value'] ?? '';
                $label     = $item['label'] ?? '';
                $link      = $item['link'] ?? null;
            @endphp
            <div
                class="group relative flex flex-col items-center text-center rounded-3xl p-8 border border-white/60 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
                style="background-color: {{ $colors['bg'] }}"
            >
                {{-- Icon circle --}}
                <div
                    class="flex items-center justify-center w-20 h-20 rounded-full mb-6 shadow-sm group-hover:scale-110 transition-transform duration-300"
                    style="background-color: {{ $colors['icon_bg'] }}"
                >
                    <div class="h-9 w-9" style="color: {{ $colors['icon_color'] }}">
                        {!! $svg !!}
                    </div>
                </div>

                {{-- Value --}}
                @if($link)
                <a href="{{ $link }}" class="text-lg font-bold text-slate-800 leading-snug hover:opacity-80 transition-opacity mb-1.5">
                    {{ $value }}
                </a>
                @else
                <p class="text-lg font-bold text-slate-800 leading-snug mb-1.5">{{ $value }}</p>
                @endif

                {{-- Label --}}
                <p class="text-sm text-slate-500">{{ $label }}</p>
            </div>
            @endforeach
        </div>
        @endif

    </div>
</section>
