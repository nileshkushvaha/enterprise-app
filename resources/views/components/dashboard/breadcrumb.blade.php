@props(['crumbs' => []])

@if(count($crumbs) > 0)
<nav class="flex items-center gap-1.5 text-xs text-slate-500 mb-6">
    <a href="{{ route('dashboard') }}" class="hover:text-slate-300 transition">Home</a>
    @foreach($crumbs as $crumb)
        <svg class="w-3 h-3 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        @if(isset($crumb['url']))
            <a href="{{ $crumb['url'] }}" class="hover:text-slate-300 transition">{{ $crumb['label'] }}</a>
        @else
            <span class="text-slate-400">{{ $crumb['label'] }}</span>
        @endif
    @endforeach
</nav>
@endif
