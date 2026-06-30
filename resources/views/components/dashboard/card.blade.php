@props([
    'title'    => null,
    'linkText' => null,
    'linkHref' => null,
])

<div class="rounded-2xl border border-white/[0.07] p-6 relative overflow-hidden group hover:border-white/[0.10] transition-all"
     style="background:rgba(255,255,255,0.03)">

    @if($title)
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white">{{ $title }}</h2>
            @if($linkText && $linkHref)
                <a href="{{ $linkHref }}" class="text-xs text-indigo-400 hover:text-indigo-300 transition">{{ $linkText }}</a>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
