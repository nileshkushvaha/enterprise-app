{{--
    Quick Actions Widget
    Each card is permission-filtered by QuickActionsWidget::getActions().
    No permission logic lives here — the view only renders what it receives.
--}}
@php $actions = $this->getActions(); @endphp

@if(count($actions))
<div class="fi-wi-quick-actions p-4">
    <h2 class="text-base font-semibold text-gray-950 dark:text-white mb-4">
        Quick Actions
    </h2>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-8">
        @foreach($actions as $action)
            @php
                $colorMap = [
                    'blue'    => 'bg-blue-50 text-blue-600 ring-blue-200 hover:bg-blue-100 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30 dark:hover:bg-blue-500/20',
                    'violet'  => 'bg-violet-50 text-violet-600 ring-violet-200 hover:bg-violet-100 dark:bg-violet-500/10 dark:text-violet-400 dark:ring-violet-500/30 dark:hover:bg-violet-500/20',
                    'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-200 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30 dark:hover:bg-emerald-500/20',
                    'amber'   => 'bg-amber-50 text-amber-600 ring-amber-200 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30 dark:hover:bg-amber-500/20',
                    'slate'   => 'bg-slate-50 text-slate-600 ring-slate-200 hover:bg-slate-100 dark:bg-slate-500/10 dark:text-slate-400 dark:ring-slate-500/30 dark:hover:bg-slate-500/20',
                    'rose'    => 'bg-rose-50 text-rose-600 ring-rose-200 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/30 dark:hover:bg-rose-500/20',
                    'cyan'    => 'bg-cyan-50 text-cyan-600 ring-cyan-200 hover:bg-cyan-100 dark:bg-cyan-500/10 dark:text-cyan-400 dark:ring-cyan-500/30 dark:hover:bg-cyan-500/20',
                    'orange'  => 'bg-orange-50 text-orange-600 ring-orange-200 hover:bg-orange-100 dark:bg-orange-500/10 dark:text-orange-400 dark:ring-orange-500/30 dark:hover:bg-orange-500/20',
                ];
                $classes = $colorMap[$action['color']] ?? $colorMap['slate'];
            @endphp

            <a
                href="{{ $action['url'] }}"
                title="{{ $action['description'] }}"
                class="flex flex-col items-center justify-center gap-2 rounded-xl p-4 ring-1 transition-colors duration-150 {{ $classes }}"
            >
                <x-filament::icon
                    :icon="$action['icon']"
                    class="h-6 w-6 shrink-0"
                />
                <span class="text-xs font-medium text-center leading-tight">
                    {{ $action['label'] }}
                </span>
            </a>
        @endforeach
    </div>
</div>
@endif
