@php
    $typeMap = [
        'url'      => ['hex' => '#6b7280', 'label' => 'URL',      'path' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'],
        'page'     => ['hex' => '#3b82f6', 'label' => 'Page',     'path' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        'post'     => ['hex' => '#22c55e', 'label' => 'Post',     'path' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z'],
        'category' => ['hex' => '#f97316', 'label' => 'Category', 'path' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'],
        'tag'      => ['hex' => '#a855f7', 'label' => 'Tag',      'path' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A2 2 0 013 9.414V5a2 2 0 012-2z'],
        'route'    => ['hex' => '#6366f1', 'label' => 'Route',    'path' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7'],
        'email'    => ['hex' => '#14b8a6', 'label' => 'Email',    'path' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        'phone'    => ['hex' => '#06b6d4', 'label' => 'Phone',    'path' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
        'anchor'   => ['hex' => '#f59e0b', 'label' => 'Anchor',   'path' => 'M13 10V3L4 14h7v7l9-11h-7z'],
    ];
@endphp
@foreach($items as $item)
@php
    $type       = $typeMap[$item['link_type']] ?? $typeMap['url'];
    $childCount = count($item['children'] ?? []);

    $now          = now();
    $publishFrom  = $item['publish_from']  ? \Carbon\Carbon::parse($item['publish_from'])  : null;
    $publishUntil = $item['publish_until'] ? \Carbon\Carbon::parse($item['publish_until']) : null;
    $isExpired    = $publishUntil && $now->isAfter($publishUntil);
    $isScheduled  = !$isExpired && $publishFrom && $now->isBefore($publishFrom);
    $isWindowed   = !$isExpired && !$isScheduled && ($publishFrom || $publishUntil);
@endphp
<li
    data-item-id="{{ $item['id'] }}"
    wire:key="nav-item-{{ $item['id'] }}"
    class="select-none"
>
    {{-- Row --}}
    <div
        class="flex items-stretch bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg mb-1.5 shadow-sm group hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-md transition-all overflow-hidden"
    >
        {{-- Colored accent strip --}}
        <div class="w-1 flex-shrink-0 rounded-l-lg" style="background-color: {{ $type['hex'] }}"></div>

        {{-- Drag handle --}}
        <div
            data-drag-handle
            class="cursor-grab active:cursor-grabbing flex items-center px-2 text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400 flex-shrink-0"
            title="Drag to reorder"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
            </svg>
        </div>

        {{-- Type icon --}}
        <div class="flex items-center pr-2 flex-shrink-0" style="color: {{ $type['hex'] }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $type['path'] }}"/>
            </svg>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0 py-2.5">
            {{-- Label row --}}
            <div class="flex items-center gap-2">
                @if($item['icon'])
                <span class="text-xs font-mono text-gray-400 dark:text-gray-500 flex-shrink-0">{{ $item['icon'] }}</span>
                @endif
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">
                    {{ $item['label'] ?: '(no label)' }}
                </span>
                @if($childCount > 0)
                <span class="inline-flex items-center px-1.5 py-0 rounded-full text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 leading-5 flex-shrink-0">
                    {{ $childCount }}
                </span>
                @endif
            </div>
            {{-- Meta row --}}
            <div class="flex items-center gap-1.5 mt-1">
                <span class="text-xs font-mono text-gray-400 dark:text-gray-500">{{ $type['label'] }}</span>

                @if($item['target'] === '_blank')
                <span class="inline-flex items-center gap-0.5 text-xs text-gray-400 dark:text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </span>
                @endif

                @if($item['visibility'] !== 'all')
                <span class="inline-flex items-center px-1.5 py-0 rounded text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 leading-5">
                    {{ ucfirst(str_replace('_', ' ', $item['visibility'])) }}
                </span>
                @endif

                @if(!$item['is_active'])
                <span class="inline-flex items-center px-1.5 py-0 rounded text-xs bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 leading-5">Inactive</span>
                @endif

                @if($isExpired)
                <span class="inline-flex items-center px-1.5 py-0 rounded text-xs bg-red-50 dark:bg-red-900/30 text-red-500 dark:text-red-400 leading-5">Expired</span>
                @elseif($isScheduled)
                <span class="inline-flex items-center px-1.5 py-0 rounded text-xs bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 leading-5">Scheduled</span>
                @elseif($isWindowed)
                <span class="inline-flex items-center px-1.5 py-0 rounded text-xs bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 leading-5">Live</span>
                @endif

                @if($item['locale'])
                <span class="inline-flex items-center px-1.5 py-0 rounded text-xs font-mono bg-violet-50 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 leading-5">{{ $item['locale'] }}</span>
                @endif

                @if($item['badge_text'])
                <span class="inline-flex items-center px-1.5 py-0 rounded text-xs text-white leading-5" style="background-color: {{ $item['badge_color'] ?: '#6b7280' }}">
                    {{ $item['badge_text'] }}
                </span>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-0.5 pr-2 flex-shrink-0">
            <button
                wire:click="editItem('{{ $item['id'] }}')"
                title="Edit"
                class="p-1.5 rounded-md text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <button
                wire:click="duplicateItem('{{ $item['id'] }}')"
                title="Duplicate"
                class="p-1.5 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors opacity-0 group-hover:opacity-100"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </button>
            <button
                wire:click="deleteItem('{{ $item['id'] }}')"
                wire:confirm="Delete '{{ addslashes($item['label']) }}'? This will also remove all child items."
                title="Delete"
                class="p-1.5 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors opacity-0 group-hover:opacity-100"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Children drop zone --}}
    <ul
        data-sortable
        data-parent="{{ $item['id'] }}"
        class="pl-5 ml-3.5 border-l-2 border-dashed border-gray-200 dark:border-gray-700 {{ empty($item['children']) ? 'min-h-[1.25rem]' : 'space-y-0' }}"
    >
        @if(!empty($item['children']))
            @include('livewire.navigation.partials.tree-item', ['items' => $item['children'], 'depth' => ($depth ?? 0) + 1, 'typeMap' => $typeMap])
        @endif
    </ul>
</li>
@endforeach
