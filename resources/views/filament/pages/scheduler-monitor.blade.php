<x-filament-panels::page>

    @php
        $tasks = $this->getTasks();
        $canRun = $this->canRunTasks();
    @endphp

    <div class="space-y-4">

        {{-- ── Task table ────────────────────────────────────────────────── --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">

            @if($tasks->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <x-filament::icon icon="heroicon-o-clock" class="h-10 w-10 text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No scheduled tasks found</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Define tasks in <code class="font-mono">routes/console.php</code></p>
                </div>
            @else
                {{-- Header --}}
                <div class="hidden lg:grid grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] gap-x-4 px-6 py-3 border-b border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-white/[0.02]">
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Command</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Frequency</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Next Run</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Last Run</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Duration</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Status</span>
                    @if($canRun)
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"></span>
                    @endif
                </div>

                {{-- Rows --}}
                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($tasks as $task)
                        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] gap-x-4 gap-y-2 px-6 py-4 items-center">

                            {{-- Command / Description --}}
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <code class="text-xs font-mono font-semibold text-primary-600 dark:text-primary-400 truncate">
                                        {{ $task['name'] }}
                                    </code>
                                    @if($task['is_closure'])
                                        <span class="shrink-0 inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20 dark:bg-purple-500/10 dark:text-purple-400 dark:ring-purple-500/20">
                                            closure
                                        </span>
                                    @endif
                                    @if($task['mutex_locked'] ?? false)
                                        <span class="shrink-0 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-medium bg-danger-50 text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/20">
                                            <x-filament::icon icon="heroicon-o-lock-closed" class="h-2.5 w-2.5" />
                                            locked
                                        </span>
                                    @endif
                                </div>
                                @if($task['description'])
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500 truncate">{{ $task['description'] }}</p>
                                @endif
                            </div>

                            {{-- Frequency --}}
                            <div>
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $task['frequency'] }}</span>
                                @if($task['frequency'] !== $task['expression'])
                                    <p class="text-[10px] font-mono text-gray-400 dark:text-gray-500 mt-0.5">{{ $task['expression'] }}</p>
                                @endif
                            </div>

                            {{-- Next Run --}}
                            <div>
                                @if($task['next_run'])
                                    <span class="text-sm text-gray-700 dark:text-gray-300" title="{{ $task['next_run']->toDateTimeString() }}">
                                        {{ $task['next_run']->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400">—</span>
                                @endif
                            </div>

                            {{-- Last Run --}}
                            <div>
                                @if($task['last_run'])
                                    <span class="text-sm text-gray-700 dark:text-gray-300" title="{{ $task['last_run']->toDateTimeString() }}">
                                        {{ $task['last_run']->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400">Never</span>
                                @endif
                            </div>

                            {{-- Duration --}}
                            <div>
                                @if($task['duration_ms'] !== null)
                                    @php
                                        $ms = $task['duration_ms'];
                                        $dur = $ms < 1000 ? "{$ms}ms" : round($ms / 1000, 2) . 's';
                                    @endphp
                                    <span class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ $dur }}</span>
                                @else
                                    <span class="text-sm text-gray-400">—</span>
                                @endif
                            </div>

                            {{-- Status badge --}}
                            <div>
                                @if($task['status'] === 'success')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                                        Success
                                    </span>
                                @elseif($task['status'] === 'failed')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-danger-50 px-2.5 py-1 text-xs font-medium text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-danger-500"></span>
                                        Failed
                                    </span>
                                @elseif($task['status'] === 'skipped')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-warning-500"></span>
                                        Skipped
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        No history
                                    </span>
                                @endif
                            </div>

                            {{-- Run Now --}}
                            @if($canRun)
                                <div class="flex justify-end">
                                    @if(! $task['is_closure'])
                                        <button
                                            wire:click="mountAction('runNow', {{ Js::from(['id' => $task['id'], 'name' => $task['name']]) }})"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-warning-700 ring-1 ring-inset ring-warning-600/20 bg-warning-50 hover:bg-warning-100 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20 dark:hover:bg-warning-500/20 transition-colors"
                                        >
                                            <x-filament::icon icon="heroicon-o-play" class="h-3 w-3" />
                                            Run Now
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500 italic">Manual run unavailable</span>
                                    @endif
                                </div>
                            @endif

                        </div>
                    @endforeach
                </div>
            @endif

        </div>

        {{-- ── Run History ─────────────────────────────────────────────── --}}
        @php $history = $this->getRecentHistory(); @endphp

        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5 text-gray-400" />
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Recent Run History</h3>
                <span class="ml-auto text-xs text-gray-400 dark:text-gray-500">Last 20 executions · pruned after 30 days</span>
            </div>

            @if($history->isEmpty())
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <p class="text-sm text-gray-400 dark:text-gray-500">No history yet. Records appear here after the first <code class="font-mono">schedule:run</code>.</p>
                </div>
            @else
                <div class="hidden lg:grid grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] gap-x-4 px-6 py-3 border-b border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-white/[0.02]">
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Command</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Ran</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Triggered By</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Duration</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Status</span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($history as $record)
                        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] gap-x-4 gap-y-1 px-6 py-3 items-center">

                            {{-- Command --}}
                            <code class="text-xs font-mono text-primary-600 dark:text-primary-400 truncate" title="{{ $record->command }}">
                                @php
                                    preg_match('/artisan\s+(.+)$/', $record->command, $m);
                                    echo $m[1] ?? $record->command;
                                @endphp
                            </code>

                            {{-- Ran at --}}
                            <span class="text-xs text-gray-500 dark:text-gray-400" title="{{ $record->ran_at->toDateTimeString() }}">
                                {{ $record->ran_at->diffForHumans() }}
                            </span>

                            {{-- Triggered by --}}
                            @if($record->triggered_by === 'manual')
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium bg-primary-50 text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-500/20 w-fit">
                                    Manual
                                </span>
                            @else
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20 w-fit">
                                    Cron
                                </span>
                            @endif

                            {{-- Duration --}}
                            <span class="text-xs font-mono text-gray-700 dark:text-gray-300">
                                {{ $record->formattedDuration() }}
                            </span>

                            {{-- Status --}}
                            @if($record->status === 'success')
                                <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-[10px] font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20 w-fit">
                                    <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span> Success
                                </span>
                            @elseif($record->status === 'failed')
                                <span class="inline-flex items-center gap-1 rounded-full bg-danger-50 px-2 py-0.5 text-[10px] font-medium text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/20 w-fit" title="{{ $record->output }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-danger-500"></span> Failed
                                </span>
                            @elseif($record->status === 'skipped')
                                <span class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2 py-0.5 text-[10px] font-medium text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20 w-fit">
                                    <span class="h-1.5 w-1.5 rounded-full bg-warning-500"></span> Skipped
                                </span>
                            @endif

                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Footer note ─────────────────────────────────────────────── --}}
        <p class="text-xs text-gray-400 dark:text-gray-500 px-1">
            History is recorded automatically when tasks run via <code class="font-mono">schedule:run</code>, and for manual runs above.
            Times are server-local ({{ config('app.timezone', 'UTC') }}).
        </p>

    </div>

    <x-filament-actions::modals />

</x-filament-panels::page>
