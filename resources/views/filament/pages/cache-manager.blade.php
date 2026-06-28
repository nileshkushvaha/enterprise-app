<x-filament-panels::page>

    <div class="space-y-6">

        {{-- ── Section 1: Infrastructure + Cache Status ────────────────────── --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            {{-- Infrastructure --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                    <x-filament::icon icon="heroicon-o-server-stack" class="h-5 w-5 text-gray-400" />
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Infrastructure</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($this->getCacheInfo() as $item)
                        @if($item['group'] === 'infrastructure')
                            <div class="flex items-center justify-between px-6 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <x-filament::icon :icon="$item['icon']" class="h-4 w-4 shrink-0 text-primary-500 dark:text-primary-400" />
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $item['label'] }}</span>
                                </div>
                                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $item['value'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Cache Status --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                    <x-filament::icon icon="heroicon-o-circle-stack" class="h-5 w-5 text-gray-400" />
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Cache Status</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($this->getCacheInfo() as $item)
                        @if($item['group'] === 'cache')
                            <div class="flex items-center justify-between px-6 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <x-filament::icon :icon="$item['icon']" class="h-4 w-4 shrink-0 text-primary-500 dark:text-primary-400" />
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $item['label'] }}</span>
                                </div>
                                @if($item['cached'])
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                                        Cached
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        Not cached
                                    </span>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

        </div>

        {{-- ── Section 2: Last Execution Result ────────────────────────────── --}}
        @if($this->processing)
            <div class="rounded-xl border border-warning-200 bg-warning-50 px-5 py-4 dark:border-warning-500/20 dark:bg-warning-500/10">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 shrink-0 animate-spin text-warning-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <p class="text-sm font-medium text-warning-700 dark:text-warning-400">Running command… please wait.</p>
                </div>
            </div>
        @endif

        @if($this->lastResult)
            @php $r = $this->lastResult; @endphp
            <div @class([
                'rounded-xl ring-1',
                'bg-success-50 ring-success-600/20 dark:bg-success-500/10 dark:ring-success-500/20' => $r['success'],
                'bg-danger-50 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-500/20'     => ! $r['success'],
            ])>
                <div class="flex items-start gap-3 px-5 py-4">
                    @if($r['success'])
                        <x-filament::icon icon="heroicon-o-check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-success-600 dark:text-success-400" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-success-800 dark:text-success-300">{{ $r['message'] }}</p>
                            @if(! empty($r['output']))
                                <pre class="mt-2 rounded-lg bg-success-100/60 dark:bg-success-900/30 p-3 text-xs text-success-900 dark:text-success-300 overflow-x-auto whitespace-pre-wrap font-mono">{{ $r['output'] }}</pre>
                            @endif
                        </div>
                    @else
                        <x-filament::icon icon="heroicon-o-x-circle" class="mt-0.5 h-5 w-5 shrink-0 text-danger-600 dark:text-danger-400" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-danger-800 dark:text-danger-300">{{ $r['message'] }}</p>
                            @if(! empty($r['output']))
                                <pre class="mt-2 rounded-lg bg-danger-100/60 dark:bg-danger-900/30 p-3 text-xs text-danger-900 dark:text-danger-300 overflow-x-auto whitespace-pre-wrap font-mono">{{ $r['output'] }}</pre>
                            @endif
                        </div>
                    @endif
                    <p class="shrink-0 text-xs text-gray-400 dark:text-gray-500 pt-0.5">{{ $r['timestamp'] }}</p>
                </div>
            </div>
        @endif

    </div>

</x-filament-panels::page>
