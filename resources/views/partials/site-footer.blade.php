{{--
    Global site footer — 4-column layout.
    Col 1: About (brand + social)
    Col 2: Our Links (footer nav)
    Col 3: Latest Posts (dynamic)
    Col 4: Contact Us (phone, email, address)

    Variables injected by layouts/frontend.blade.php and layouts/page.blade.php:
    $appName, $logo, $footerText, $footerCopyright, $supportEmail, $supportPhone, $address
--}}
@php
    use App\Models\Post;
    $footerPosts = Post::query()
        ->published()
        ->with('media')
        ->latest('published_at')
        ->limit(3)
        ->get();
@endphp

<footer class="relative overflow-hidden border-t border-white/[0.06]"
        style="background: rgba(5,8,15,.98)">

    {{-- Decorative background orbs --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -bottom-40 -left-20 w-80 h-80 rounded-full blur-3xl"
             style="background: radial-gradient(circle, rgba(99,102,241,0.08), transparent)"></div>
        <div class="absolute -top-20 right-0 w-64 h-64 rounded-full blur-3xl"
             style="background: radial-gradient(circle, rgba(139,92,246,0.06), transparent)"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-10">

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-8">

            {{-- ── Col 1: About Us ── --}}
            <div>
                <h4 class="text-white font-bold text-base mb-1">About Us</h4>
                <div class="w-8 h-0.5 bg-gradient-to-r from-indigo-500 to-violet-500 mb-5 rounded-full"></div>

                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 mb-4 group">
                    @if($logo ?? null)
                        <img src="{{ $logo }}" alt="{{ $appName }}" class="h-8 w-auto object-contain">
                    @else
                        <div class="h-8 w-8 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/20 flex-shrink-0">
                            <span class="text-white font-extrabold text-sm leading-none">{{ mb_substr($appName ?? 'E', 0, 1) }}</span>
                        </div>
                    @endif
                    <span class="text-white font-bold text-base tracking-tight group-hover:text-indigo-300 transition-colors">{{ $appName }}</span>
                </a>

                @if($footerText ?? null)
                    <p class="text-slate-500 text-sm leading-relaxed mb-6">{{ $footerText }}</p>
                @else
                    <p class="text-slate-500 text-sm leading-relaxed mb-6">Building personalised learning experiences that connect students with world-class educators.</p>
                @endif

                {{-- Social icons --}}
                <div class="flex items-center gap-2.5">
                    @foreach([
                        ['label' => 'Facebook',  'path' => 'M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z'],
                        ['label' => 'Instagram', 'path' => 'M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zm1.5-4.87h.01M6.5 20.5h11a3 3 0 003-3v-11a3 3 0 00-3-3h-11a3 3 0 00-3 3v11a3 3 0 003 3z'],
                        ['label' => 'Twitter',   'path' => 'M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z'],
                        ['label' => 'YouTube',   'path' => 'M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58A2.78 2.78 0 003.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58zM9.75 15.02V8.98L15.5 12l-5.75 3.02z'],
                    ] as $social)
                    <a href="#" aria-label="{{ $social['label'] }}"
                       class="w-9 h-9 rounded-xl border border-white/[0.08] flex items-center justify-center text-slate-500 hover:text-indigo-400 hover:border-indigo-500/40 hover:bg-indigo-500/10 transition-all duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $social['path'] }}"/>
                        </svg>
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- ── Col 2: Our Links (footer nav) ── --}}
            <div>
                <h4 class="text-white font-bold text-base mb-1">Our Links</h4>
                <div class="w-8 h-0.5 bg-gradient-to-r from-indigo-500 to-violet-500 mb-5 rounded-full"></div>
                <x-navigation location="footer" />
            </div>

            {{-- ── Col 3: Latest Posts ── --}}
            <div>
                <h4 class="text-white font-bold text-base mb-1">Latest Post</h4>
                <div class="w-8 h-0.5 bg-gradient-to-r from-indigo-500 to-violet-500 mb-5 rounded-full"></div>

                @if($footerPosts->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($footerPosts as $post)
                        <a href="{{ route('blog.show', $post->slug) }}"
                           class="flex items-start gap-3 group">
                            {{-- Thumbnail --}}
                            @php $thumb = $post->getFirstMediaUrl('thumbnail') ?: $post->getFirstMediaUrl(); @endphp
                            @if($thumb)
                                <img src="{{ $thumb }}" alt="{{ $post->title }}"
                                     class="w-16 h-12 rounded-lg object-cover flex-shrink-0 border border-white/[0.06] group-hover:border-indigo-500/30 transition-colors">
                            @else
                                <div class="w-16 h-12 rounded-lg flex-shrink-0 bg-gradient-to-br from-indigo-900/40 to-violet-900/30 border border-white/[0.06] flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-500/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                </div>
                            @endif
                            <div class="min-w-0">
                                <p class="text-slate-400 text-xs leading-snug line-clamp-2 group-hover:text-slate-300 transition-colors mb-1">
                                    {{ $post->title }}
                                </p>
                                @if($post->published_at)
                                <span class="text-xs text-slate-600">{{ $post->published_at->format('M j, Y') }}</span>
                                @endif
                            </div>
                        </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-slate-600 text-sm">No posts yet.</p>
                @endif
            </div>

            {{-- ── Col 4: Contact Us ── --}}
            <div>
                <h4 class="text-white font-bold text-base mb-1">Contact Us</h4>
                <div class="w-8 h-0.5 bg-gradient-to-r from-indigo-500 to-violet-500 mb-5 rounded-full"></div>

                <div class="space-y-4">
                    @if($supportPhone ?? null)
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-indigo-500/20">
                            <svg class="text-white" style="width:1.125rem;height:1.125rem" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 mb-0.5">Phone</p>
                            <a href="tel:{{ $supportPhone }}" class="text-slate-300 text-sm hover:text-indigo-400 transition-colors">{{ $supportPhone }}</a>
                        </div>
                    </div>
                    @endif

                    @if($supportEmail ?? null)
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-indigo-500/20">
                            <svg class="text-white" style="width:1.125rem;height:1.125rem" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 mb-0.5">Email</p>
                            <a href="mailto:{{ $supportEmail }}" class="text-slate-300 text-sm hover:text-indigo-400 transition-colors break-all">{{ $supportEmail }}</a>
                        </div>
                    </div>
                    @endif

                    @if($address ?? null)
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-indigo-500/20">
                            <svg class="text-white" style="width:1.125rem;height:1.125rem" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 mb-0.5">Address</p>
                            <span class="text-slate-300 text-sm leading-relaxed">{{ $address }}</span>
                        </div>
                    </div>
                    @endif

                    @if(!($supportPhone ?? null) && !($supportEmail ?? null) && !($address ?? null))
                    <p class="text-slate-600 text-sm">Configure contact details in <span class="text-slate-500">Settings → General</span>.</p>
                    @endif
                </div>
            </div>

        </div>

        {{-- ── Copyright bar ── --}}
        <div class="mt-14 pt-8 border-t border-white/[0.04] flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-xs text-slate-600 text-center sm:text-left">
                {!! $footerCopyright ?? ('&copy; ' . date('Y') . ' ' . ($appName ?? config('app.name')) . '. All rights reserved.') !!}
            </p>
            <div class="flex items-center gap-5">
                <a href="{{ url('/privacy-policy') }}" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Privacy Policy</a>
                <a href="{{ url('/terms-of-service') }}" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Terms of Service</a>
                @if(Route::has('search.index'))
                <a href="{{ route('search.index') }}" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Search</a>
                @endif
            </div>
        </div>

    </div>
</footer>
