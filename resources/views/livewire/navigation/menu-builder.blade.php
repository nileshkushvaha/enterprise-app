<div
    x-data="navigationMenuBuilder()"
    x-init="init()"
    class="fi-nav-builder"
>

    @once
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js" defer></script>
    @endonce

    {{-- ── Two-panel layout ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">

        {{-- ════════════════════════════════════════════════════════════════
             LEFT PANEL — Available Links
        ════════════════════════════════════════════════════════════════════ --}}
        <div class="lg:col-span-1 space-y-2">

            <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-widest px-1 mb-3">Add items</p>

            {{-- Pages --}}
            <div x-data="{ open: true }" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800">
                <button type="button" @click="open = !open"
                    class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors text-left"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" style="color:#3b82f6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="flex-1">Pages</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400 transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                    <div class="p-3 space-y-2">
                        <div class="relative">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-2.5 top-2 h-3.5 w-3.5 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input wire:model.live.debounce.300ms="searchPages" type="text" placeholder="Search pages…"
                                class="w-full text-sm pl-8 pr-3 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        </div>
                        <ul class="space-y-0.5 max-h-52 overflow-y-auto">
                            @forelse($this->pages as $page)
                            <li>
                                <button type="button" wire:click="addPage('{{ $page->id }}')"
                                    class="w-full text-left px-2.5 py-2 text-sm rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-700 dark:text-gray-300 hover:text-blue-700 dark:hover:text-blue-300 transition-colors flex items-center gap-2 group"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 group-hover:text-blue-400 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <span class="truncate">{{ $page->title }}</span>
                                </button>
                            </li>
                            @empty
                            <li class="text-xs text-gray-400 px-2 py-3 text-center italic">No pages found</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Posts --}}
            <div x-data="{ open: false }" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800">
                <button type="button" @click="open = !open"
                    class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors text-left"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" style="color:#22c55e" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                    </svg>
                    <span class="flex-1">Posts</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400 transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                    <div class="p-3 space-y-2">
                        <div class="relative">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-2.5 top-2 h-3.5 w-3.5 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input wire:model.live.debounce.300ms="searchPosts" type="text" placeholder="Search posts…"
                                class="w-full text-sm pl-8 pr-3 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        </div>
                        <ul class="space-y-0.5 max-h-52 overflow-y-auto">
                            @forelse($this->posts as $post)
                            <li>
                                <button type="button" wire:click="addPost('{{ $post->id }}')"
                                    class="w-full text-left px-2.5 py-2 text-sm rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 text-gray-700 dark:text-gray-300 hover:text-green-700 dark:hover:text-green-300 transition-colors flex items-center gap-2 group"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 group-hover:text-green-400 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <span class="truncate">{{ $post->title }}</span>
                                </button>
                            </li>
                            @empty
                            <li class="text-xs text-gray-400 px-2 py-3 text-center italic">No posts found</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Categories --}}
            <div x-data="{ open: false }" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800">
                <button type="button" @click="open = !open"
                    class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors text-left"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" style="color:#f97316" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                    <span class="flex-1">Categories</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400 transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                    <div class="p-3 space-y-2">
                        <div class="relative">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-2.5 top-2 h-3.5 w-3.5 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input wire:model.live.debounce.300ms="searchCategories" type="text" placeholder="Search categories…"
                                class="w-full text-sm pl-8 pr-3 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        </div>
                        <ul class="space-y-0.5 max-h-52 overflow-y-auto">
                            @forelse($this->categories as $category)
                            <li>
                                <button type="button" wire:click="addCategory('{{ $category->id }}')"
                                    class="w-full text-left px-2.5 py-2 text-sm rounded-lg hover:bg-orange-50 dark:hover:bg-orange-900/20 text-gray-700 dark:text-gray-300 hover:text-orange-700 dark:hover:text-orange-300 transition-colors flex items-center gap-2 group"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 group-hover:text-orange-400 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <span class="truncate">{{ $category->name }}</span>
                                </button>
                            </li>
                            @empty
                            <li class="text-xs text-gray-400 px-2 py-3 text-center italic">No categories found</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Tags --}}
            <div x-data="{ open: false }" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800">
                <button type="button" @click="open = !open"
                    class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors text-left"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" style="color:#a855f7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A2 2 0 013 9.414V5a2 2 0 012-2z"/>
                    </svg>
                    <span class="flex-1">Tags</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400 transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                    <div class="p-3 space-y-2">
                        <div class="relative">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-2.5 top-2 h-3.5 w-3.5 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input wire:model.live.debounce.300ms="searchTags" type="text" placeholder="Search tags…"
                                class="w-full text-sm pl-8 pr-3 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        </div>
                        <ul class="space-y-0.5 max-h-52 overflow-y-auto">
                            @forelse($this->tags as $tag)
                            <li>
                                <button type="button" wire:click="addTag('{{ $tag->id }}')"
                                    class="w-full text-left px-2.5 py-2 text-sm rounded-lg hover:bg-violet-50 dark:hover:bg-violet-900/20 text-gray-700 dark:text-gray-300 hover:text-violet-700 dark:hover:text-violet-300 transition-colors flex items-center gap-2 group"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 group-hover:text-violet-400 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <span class="truncate">{{ $tag->name }}</span>
                                </button>
                            </li>
                            @empty
                            <li class="text-xs text-gray-400 px-2 py-3 text-center italic">No tags found</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Custom Links — tabbed --}}
            <div x-data="{ open: false }" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800">
                <button type="button" @click="open = !open"
                    class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors text-left"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    <span class="flex-1">Custom Links</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400 transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                    <div x-data="{ linkTab: 'url' }" class="p-3 space-y-3">
                        {{-- Tab bar --}}
                        <div class="flex gap-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5">
                            @foreach([
                                ['key' => 'url',    'label' => 'URL'],
                                ['key' => 'route',  'label' => 'Route'],
                                ['key' => 'email',  'label' => 'Email'],
                                ['key' => 'phone',  'label' => 'Phone'],
                                ['key' => 'anchor', 'label' => '#'],
                            ] as $tab)
                            <button
                                type="button"
                                @click="linkTab = '{{ $tab['key'] }}'"
                                :class="linkTab === '{{ $tab['key'] }}' ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                                class="flex-1 py-1 text-xs font-medium rounded-md transition-all"
                            >{{ $tab['label'] }}</button>
                            @endforeach
                        </div>

                        {{-- URL --}}
                        <div x-show="linkTab === 'url'" class="space-y-2">
                            <input wire:model="customUrlLabel" type="text" placeholder="Label" class="w-full text-sm px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <div class="flex gap-2">
                                <input wire:model="customUrlInput" type="url" placeholder="https://…" class="flex-1 text-sm px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                <button type="button" wire:click="addCustomUrl" class="px-3 py-1.5 text-xs font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">Add</button>
                            </div>
                        </div>

                        {{-- Route --}}
                        <div x-show="linkTab === 'route'" class="space-y-2">
                            <input wire:model="routeLabel" type="text" placeholder="Label" class="w-full text-sm px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <div class="flex gap-2">
                                <input wire:model="routeNameInput" type="text" placeholder="route.name" class="flex-1 text-sm px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                <button type="button" wire:click="addRoute" class="px-3 py-1.5 text-xs font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">Add</button>
                            </div>
                        </div>

                        {{-- Email --}}
                        <div x-show="linkTab === 'email'" class="space-y-2">
                            <input wire:model="emailLabel" type="text" placeholder="Label" class="w-full text-sm px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <div class="flex gap-2">
                                <input wire:model="emailInput" type="email" placeholder="hello@example.com" class="flex-1 text-sm px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                <button type="button" wire:click="addEmail" class="px-3 py-1.5 text-xs font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">Add</button>
                            </div>
                        </div>

                        {{-- Phone --}}
                        <div x-show="linkTab === 'phone'" class="space-y-2">
                            <input wire:model="phoneLabel" type="text" placeholder="Label" class="w-full text-sm px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <div class="flex gap-2">
                                <input wire:model="phoneInput" type="tel" placeholder="+1 (555) 000-0000" class="flex-1 text-sm px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                <button type="button" wire:click="addPhone" class="px-3 py-1.5 text-xs font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">Add</button>
                            </div>
                        </div>

                        {{-- Anchor --}}
                        <div x-show="linkTab === 'anchor'" class="space-y-2">
                            <input wire:model="anchorLabel" type="text" placeholder="Label" class="w-full text-sm px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <div class="flex gap-2">
                                <input wire:model="anchorInput" type="text" placeholder="#section-id" class="flex-1 text-sm px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                <button type="button" wire:click="addAnchor" class="px-3 py-1.5 text-xs font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">Add</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>{{-- /LEFT PANEL --}}

        {{-- ════════════════════════════════════════════════════════════════
             RIGHT PANEL — Navigation Structure
        ════════════════════════════════════════════════════════════════════ --}}
        <div class="lg:col-span-2">

            <div class="flex items-center justify-between mb-3 px-1">
                <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Menu structure</p>
                @if(!empty($treeItems))
                <span class="text-xs text-gray-400 dark:text-gray-500">
                    {{ count($treeItems) }} root {{ Str::plural('item', count($treeItems)) }} · drag to reorder
                </span>
                @endif
            </div>

            @if(empty($treeItems))
            <div class="flex flex-col items-center justify-center py-20 px-6 bg-gray-50 dark:bg-gray-800/40 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl text-center">
                <div class="w-12 h-12 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-300 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h7"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No items yet</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Add items from the panel on the left.</p>
            </div>
            @else
            <div class="bg-gray-50/70 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <ul
                    id="nav-tree-root"
                    data-sortable
                    data-sortable-root="true"
                    data-parent="null"
                    class="space-y-0"
                >
                    @include('livewire.navigation.partials.tree-item', ['items' => $treeItems, 'depth' => 0])
                </ul>
            </div>
            @endif

        </div>{{-- /RIGHT PANEL --}}
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         EDIT SLIDE-OVER
    ════════════════════════════════════════════════════════════════════════ --}}
    <div
        x-show="$wire.showSlideOver"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-hidden"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-gray-900/50 dark:bg-gray-950/70 backdrop-blur-sm" @click="$wire.cancelEdit()"></div>

        {{-- Panel --}}
        <div
            x-show="$wire.showSlideOver"
            x-data="{ activeTab: 'basic' }"
            x-transition:enter="ease-out duration-250"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-gray-800 shadow-2xl flex flex-col"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Edit Item</h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Customize label, appearance &amp; visibility</p>
                </div>
                <button type="button" @click="$wire.cancelEdit()"
                    class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Tab nav --}}
            <div class="flex border-b border-gray-200 dark:border-gray-700 px-4 flex-shrink-0">
                @foreach([
                    ['key' => 'basic',      'label' => 'Basic',      'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                    ['key' => 'appearance', 'label' => 'Appearance', 'icon' => 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01'],
                    ['key' => 'visibility', 'label' => 'Visibility', 'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'],
                    ['key' => 'scheduling', 'label' => 'Scheduling', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ] as $tab)
                <button
                    type="button"
                    @click="activeTab = '{{ $tab['key'] }}'"
                    :class="activeTab === '{{ $tab['key'] }}'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                    class="flex items-center gap-1.5 py-3 px-1 mr-4 text-xs font-medium border-b-2 transition-colors whitespace-nowrap"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/>
                    </svg>
                    {{ $tab['label'] }}
                </button>
                @endforeach
            </div>

            {{-- Scrollable form area --}}
            <div class="flex-1 overflow-y-auto">

                {{-- ── BASIC TAB ──────────────────────────────────────────── --}}
                <div x-show="activeTab === 'basic'" class="px-6 py-5 space-y-5">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Label <span class="text-red-500">*</span>
                        </label>
                        <input
                            wire:model="editForm.label"
                            type="text"
                            class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            placeholder="Menu item label"
                        >
                        @error('editForm.label')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Icon</label>
                        <input
                            wire:model="editForm.icon"
                            type="text"
                            class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            placeholder="heroicon-o-home"
                        >
                        <p class="mt-1 text-xs text-gray-400">Heroicon name or custom CSS class</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Open in</label>
                            <select
                                wire:model="editForm.target"
                                class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            >
                                <option value="_self">Same window</option>
                                <option value="_blank">New tab</option>
                                <option value="_parent">Parent frame</option>
                                <option value="_top">Full window</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Rel</label>
                            <input
                                wire:model="editForm.rel"
                                type="text"
                                class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                placeholder="noopener"
                            >
                        </div>
                    </div>

                </div>

                {{-- ── APPEARANCE TAB ─────────────────────────────────────── --}}
                <div x-show="activeTab === 'appearance'" class="px-6 py-5 space-y-5">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Badge</label>
                        <div class="flex gap-3 items-center">
                            <input
                                wire:model="editForm.badge_text"
                                type="text"
                                class="flex-1 px-3 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                placeholder="Badge text (e.g. New)"
                                maxlength="50"
                            >
                            <div class="flex-shrink-0 flex items-center gap-2">
                                <input
                                    wire:model="editForm.badge_color"
                                    type="color"
                                    class="h-9 w-9 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer p-0.5 bg-white dark:bg-gray-700"
                                    title="Badge color"
                                >
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Leave blank to hide badge.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">CSS Class</label>
                            <input
                                wire:model="editForm.css_class"
                                type="text"
                                class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                placeholder="my-link highlight"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">HTML ID</label>
                            <input
                                wire:model="editForm.css_id"
                                type="text"
                                class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                placeholder="nav-home"
                            >
                        </div>
                    </div>

                </div>

                {{-- ── VISIBILITY TAB ─────────────────────────────────────── --}}
                <div x-show="activeTab === 'visibility'" class="px-6 py-5 space-y-5">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Show to</label>
                        <select
                            wire:model.live="editForm.visibility"
                            class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        >
                            <option value="all">Everyone</option>
                            <option value="guest">Guests only</option>
                            <option value="auth">Logged-in users</option>
                            <option value="roles">Specific roles</option>
                            <option value="permissions">Specific permissions</option>
                        </select>
                    </div>

                    @if(($editForm['visibility'] ?? '') === 'roles')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Required Roles</label>
                        <div class="space-y-1 max-h-40 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-2">
                            @foreach($this->availableRoles as $role)
                            <label class="flex items-center gap-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 px-2 py-1.5 rounded-md">
                                <input type="checkbox" wire:model="editForm.required_role_ids" value="{{ $role->id }}"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $role->name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if(($editForm['visibility'] ?? '') === 'permissions')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Required Permissions</label>
                        <div class="space-y-1 max-h-40 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-2">
                            @foreach($this->availablePermissions as $permission)
                            <label class="flex items-center gap-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 px-2 py-1.5 rounded-md">
                                <input type="checkbox" wire:model="editForm.required_permission_ids" value="{{ $permission->id }}"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $permission->name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="space-y-1 rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                        {{-- Active toggle --}}
                        <div class="flex items-center justify-between px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Active</p>
                                <p class="text-xs text-gray-400">Hidden from all visitors when off</p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="$wire.editForm.is_active ? 'true' : 'false'"
                                @click="$wire.set('editForm.is_active', !$wire.editForm.is_active)"
                                :class="$wire.editForm.is_active ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                            >
                                <span :class="$wire.editForm.is_active ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                            </button>
                        </div>
                        {{-- Modal toggle --}}
                        <div class="flex items-center justify-between px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Open in Modal</p>
                                <p class="text-xs text-gray-400">Opens link in a modal dialog</p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="$wire.editForm.open_in_modal ? 'true' : 'false'"
                                @click="$wire.set('editForm.open_in_modal', !$wire.editForm.open_in_modal)"
                                :class="$wire.editForm.open_in_modal ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                            >
                                <span :class="$wire.editForm.open_in_modal ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                            </button>
                        </div>
                    </div>

                </div>

                {{-- ── SCHEDULING TAB ─────────────────────────────────────── --}}
                <div x-show="activeTab === 'scheduling'" class="px-6 py-5 space-y-5">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Locale</label>
                        <input
                            type="text"
                            wire:model="editForm.locale"
                            placeholder="e.g. en, fr, en-US"
                            maxlength="10"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        >
                        @error('editForm.locale')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-400">Leave blank to show in all locales.</p>
                    </div>

                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 space-y-4">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Publish window</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">From</label>
                                <input
                                    type="datetime-local"
                                    wire:model="editForm.publish_from"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-2.5 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                >
                                @error('editForm.publish_from')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Until</label>
                                <input
                                    type="datetime-local"
                                    wire:model="editForm.publish_until"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-2.5 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                >
                                @error('editForm.publish_until')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Live scheduling status badge --}}
                        <div
                            x-data="{
                                get status() {
                                    const from  = $wire.editForm.publish_from;
                                    const until = $wire.editForm.publish_until;
                                    if (!from && !until) return null;
                                    const now      = Date.now();
                                    const fromMs   = from  ? new Date(from).getTime()  : null;
                                    const untilMs  = until ? new Date(until).getTime() : null;
                                    if (untilMs !== null && now > untilMs) return 'expired';
                                    if (fromMs  !== null && now < fromMs)  return 'scheduled';
                                    return 'active';
                                }
                            }"
                        >
                            <template x-if="status === 'scheduled'">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                                    Scheduled — not yet live
                                </span>
                            </template>
                            <template x-if="status === 'expired'">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                    Expired — no longer shown
                                </span>
                            </template>
                            <template x-if="status === 'active'">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Currently live
                                </span>
                            </template>
                        </div>
                    </div>

                    <p class="text-xs text-gray-400">Leave both fields empty to always show this item.</p>

                </div>

            </div>{{-- /scrollable area --}}

            {{-- Footer --}}
            <div class="flex items-center justify-between gap-3 px-6 py-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
                <button
                    type="button"
                    @click="$wire.cancelEdit()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    wire:click="saveItem"
                    wire:loading.attr="disabled"
                    class="px-5 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-60 transition-colors flex items-center gap-2"
                >
                    <span wire:loading.remove wire:target="saveItem">Save changes</span>
                    <span wire:loading wire:target="saveItem" class="flex items-center gap-2">
                        <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Saving…
                    </span>
                </button>
            </div>

        </div>{{-- /panel --}}
    </div>{{-- /slide-over --}}

</div>

@script
<script>
window.navigationMenuBuilder = function () {
    return {
        sortables: [],

        init() {
            this.$nextTick(() => this.initSortables());

            this.$wire.on('navigation-tree-updated', () => {
                this.$nextTick(() => this.initSortables());
            });
        },

        initSortables() {
            this.sortables.forEach(s => s.destroy());
            this.sortables = [];

            if (typeof Sortable === 'undefined') {
                setTimeout(() => this.initSortables(), 200);
                return;
            }

            this.$el.querySelectorAll('[data-sortable]').forEach(list => {
                this.sortables.push(
                    new Sortable(list, {
                        group: {
                            name: 'nav-items',
                            put: true,
                            pull: true,
                        },
                        handle: '[data-drag-handle]',
                        animation: 150,
                        fallbackOnBody: true,
                        swapThreshold: 0.6,
                        ghostClass: 'opacity-40',
                        chosenClass: 'ring-2 ring-primary-400',
                        onEnd: () => this.save(),
                    })
                );
            });
        },

        save() {
            const root = this.$el.querySelector('[data-sortable-root]');
            if (!root) return;

            const items = this.serialize(root, null);
            this.$wire.reorder(items);
        },

        serialize(list, parentId) {
            const items = [];
            let order = 0;

            list.querySelectorAll(':scope > [data-item-id]').forEach(el => {
                const id = el.dataset.itemId;
                items.push({ id, parentId, sortOrder: order++ });

                const nested = el.querySelector('[data-sortable]');
                if (nested) {
                    items.push(...this.serialize(nested, id));
                }
            });

            return items;
        },
    };
};
</script>
@endscript
