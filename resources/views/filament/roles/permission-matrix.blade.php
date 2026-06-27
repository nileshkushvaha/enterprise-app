{{--
    Enterprise Permission Matrix
    ─────────────────────────────
    Module → permission chips UI, similar to enterprise ERP systems.
    State synced to parent Livewire page via $wire.selectedPermissions.

    Variables injected via viewData():
        $grouped         – Collection of grouped modules with permissions
        $allPermissions  – array<string> flat list of all permission names
        $total           – int total permission count
--}}

@php
    $matrixId      = 'pm_' . md5(uniqid('', true));
    $groupedData   = $grouped->map(fn($g) => [
        'module'      => $g['module'],
        'title'       => $g['title'],
        'permissions' => $g['permissions']->toArray(),
    ])->values()->all();
@endphp

{{-- Data is passed via window object to avoid breaking x-data HTML attribute with JSON double quotes --}}
<script>
    window['{{ $matrixId }}'] = {
        groups: @js($groupedData),
        perms:  @js($allPermissions)
    };
</script>

<div
    x-data="{
        selected: $wire.entangle('selectedPermissions'),
        search: '',
        expandedModules: {},
        allGroups: window['{{ $matrixId }}'].groups,
        allPermissions: window['{{ $matrixId }}'].perms,

        get filteredGroups() {
            if (!this.search.trim()) return this.allGroups;
            const q = this.search.toLowerCase().trim();
            return this.allGroups.reduce((acc, group) => {
                const titleMatch = group.title.toLowerCase().includes(q);
                const matchedPerms = group.permissions.filter(p =>
                    p.label.toLowerCase().includes(q) || p.name.toLowerCase().includes(q)
                );
                if (titleMatch || matchedPerms.length > 0) {
                    acc.push({ ...group, permissions: titleMatch ? group.permissions : matchedPerms });
                }
                return acc;
            }, []);
        },

        get selectedCount() { return this.selected.length; },

        isChecked(name)  { return this.selected.includes(name); },

        toggle(name) {
            const idx = this.selected.indexOf(name);
            if (idx >= 0) this.selected.splice(idx, 1);
            else this.selected.push(name);
        },

        modulePermNames(group) { return group.permissions.map(p => p.name); },

        isModuleFullySelected(group) {
            const p = this.modulePermNames(group);
            return p.length > 0 && p.every(n => this.selected.includes(n));
        },

        isModulePartiallySelected(group) {
            const p = this.modulePermNames(group);
            return p.some(n => this.selected.includes(n)) && !this.isModuleFullySelected(group);
        },

        toggleModule(group) {
            const perms = this.modulePermNames(group);
            if (this.isModuleFullySelected(group)) {
                perms.forEach(p => { const i = this.selected.indexOf(p); if (i >= 0) this.selected.splice(i, 1); });
            } else {
                perms.forEach(p => { if (!this.selected.includes(p)) this.selected.push(p); });
            }
        },

        selectAll()  { this.selected = [...this.allPermissions]; },
        clearAll()   { this.selected = []; },
        expandAll()  { this.allGroups.forEach(g => this.expandedModules[g.module] = true); },
        collapseAll(){ this.expandedModules = {}; },
        isExpanded(m){ return this.expandedModules[m] !== false; },
        toggleExpand(m){ this.expandedModules[m] = !this.isExpanded(m); },

        init() { this.expandAll(); },
    }"
    x-init="init()"
    class="space-y-0"
>

    {{-- ── TOOLBAR ────────────────────────────────────────────────────── --}}
    <div class="rounded-t-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-800/80 px-4 py-3">
        <div class="flex flex-wrap items-center gap-3">

            {{-- Search --}}
            <div class="relative flex-1 min-w-52">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                </div>
                <input
                    type="text"
                    x-model="search"
                    placeholder="Search modules or permissions…"
                    class="block w-full rounded-lg border border-gray-300 dark:border-white/10
                           bg-white dark:bg-gray-900 py-1.5 pl-9 pr-3 text-sm
                           text-gray-900 dark:text-gray-100 placeholder-gray-400
                           focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
            </div>

            {{-- Buttons --}}
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" @click="selectAll()"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold
                           bg-primary-600 text-white hover:bg-primary-700 transition-colors shadow-sm">
                    <x-heroicon-o-check-circle class="h-3.5 w-3.5" />
                    Select All
                </button>
                <button type="button" @click="clearAll()"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold
                           border border-gray-300 dark:border-white/10 bg-white dark:bg-gray-900
                           text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <x-heroicon-o-x-circle class="h-3.5 w-3.5" />
                    Clear All
                </button>
                <button type="button" @click="expandAll()"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold
                           border border-gray-300 dark:border-white/10 bg-white dark:bg-gray-900
                           text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <x-heroicon-o-chevron-down class="h-3.5 w-3.5" />
                    Expand All
                </button>
                <button type="button" @click="collapseAll()"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold
                           border border-gray-300 dark:border-white/10 bg-white dark:bg-gray-900
                           text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <x-heroicon-o-chevron-up class="h-3.5 w-3.5" />
                    Collapse All
                </button>
            </div>

            {{-- Counter badge --}}
            <div class="ml-auto flex items-center gap-2 rounded-lg bg-white dark:bg-gray-900
                        border border-gray-200 dark:border-white/10 px-3 py-1.5 shadow-sm">
                <div class="h-2 w-2 rounded-full"
                    :class="selectedCount > 0 ? 'bg-primary-500' : 'bg-gray-300 dark:bg-gray-600'">
                </div>
                <span class="text-sm font-bold text-gray-900 dark:text-gray-100">
                    <span x-text="selectedCount"></span>
                    <span class="text-gray-400 font-normal text-xs"> / {{ $total }}</span>
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400 hidden sm:inline">permissions selected</span>
            </div>
        </div>

        {{-- Progress bar --}}
        <div class="mt-2.5 flex items-center gap-2">
            <div class="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                <div
                    class="h-full bg-gradient-to-r from-primary-500 to-primary-400 rounded-full transition-all duration-300"
                    :style="`width: ${Math.round((selectedCount / {{ $total }}) * 100)}%`"
                ></div>
            </div>
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 w-8 text-right"
                x-text="`${Math.round((selectedCount / {{ $total }}) * 100)}%`"
            ></span>
        </div>
    </div>

    {{-- ── TABLE ──────────────────────────────────────────────────────── --}}
    <div class="border-x border-gray-200 dark:border-white/10">

        {{-- Header row --}}
        <div class="flex border-b border-gray-200 dark:border-white/10
                    bg-gray-100 dark:bg-gray-800 text-xs font-semibold
                    text-gray-500 dark:text-gray-400 uppercase tracking-wider">
            <div class="w-48 flex-shrink-0 px-4 py-2.5 border-r border-gray-200 dark:border-white/10">
                Module
            </div>
            <div class="flex-1 px-4 py-2.5">
                Permissions
            </div>
        </div>

        {{-- Empty state --}}
        <template x-if="filteredGroups.length === 0">
            <div class="flex flex-col items-center justify-center py-10 text-center bg-white dark:bg-gray-900">
                <x-heroicon-o-funnel class="h-7 w-7 text-gray-300 dark:text-gray-600 mb-2" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No permissions match your search</p>
                <button type="button" @click="search = ''"
                    class="mt-1.5 text-xs text-primary-600 dark:text-primary-400 hover:underline">
                    Clear search
                </button>
            </div>
        </template>

        {{-- Module rows --}}
        <template x-for="(group, gIdx) in filteredGroups" :key="group.module">
            <div
                class="flex border-b border-gray-200 dark:border-white/10 last:border-b-0"
                :class="gIdx % 2 === 0
                    ? 'bg-white dark:bg-gray-900/50'
                    : 'bg-gray-50/60 dark:bg-gray-900/20'"
            >
                {{-- Module cell --}}
                <div class="w-48 flex-shrink-0 px-4 py-4 border-r border-gray-200 dark:border-white/10
                            flex flex-col justify-start gap-2.5">

                    {{-- Module name + expand --}}
                    <button
                        type="button"
                        @click="toggleExpand(group.module)"
                        class="flex items-start gap-1.5 w-full text-left group"
                    >
                        <svg class="h-3.5 w-3.5 mt-0.5 text-gray-400 transition-transform flex-shrink-0"
                            :class="isExpanded(group.module) ? 'rotate-90' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span
                            class="text-sm font-bold text-gray-800 dark:text-gray-100
                                   group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors leading-tight"
                            x-text="group.title"
                        ></span>
                    </button>

                    {{-- Select all module --}}
                    <label class="flex items-center gap-2 cursor-pointer select-none pl-5">
                        <input
                            type="checkbox"
                            :checked="isModuleFullySelected(group)"
                            :indeterminate="isModulePartiallySelected(group)"
                            @change="toggleModule(group)"
                            class="rounded border-gray-300 dark:border-gray-600 text-primary-600
                                   focus:ring-primary-500 cursor-pointer h-3.5 w-3.5"
                        >
                        <span class="text-xs text-gray-500 dark:text-gray-400">Select all</span>
                    </label>

                    {{-- Count badge --}}
                    <div class="pl-5">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                            :class="isModuleFullySelected(group)
                                ? 'bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300'
                                : isModulePartiallySelected(group)
                                    ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'
                                    : 'bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-gray-400'"
                        >
                            <span x-text="group.permissions.filter(p => selected.includes(p.name)).length"></span>
                            <span class="mx-0.5 opacity-40">/</span>
                            <span x-text="group.permissions.length"></span>
                        </span>
                    </div>
                </div>

                {{-- Permissions chips cell --}}
                <div class="flex-1 px-4 py-4">

                    {{-- Expanded: show chips --}}
                    <div
                        x-show="isExpanded(group.module)"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                    >
                        <div class="flex flex-wrap gap-2">
                            <template x-for="perm in group.permissions" :key="perm.name">
                                <label
                                    class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1
                                           text-xs font-medium cursor-pointer select-none
                                           transition-all duration-100 ease-in-out"
                                    :class="isChecked(perm.name)
                                        ? 'border-primary-400 dark:border-primary-500/70 bg-primary-50 dark:bg-primary-900/25 text-primary-700 dark:text-primary-300 shadow-sm'
                                        : 'border-gray-200 dark:border-white/[0.08] bg-white dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 hover:border-primary-300 dark:hover:border-primary-600/50 hover:text-primary-600 dark:hover:text-primary-400'"
                                    :title="perm.name"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="isChecked(perm.name)"
                                        @change="toggle(perm.name)"
                                        class="h-3 w-3 rounded text-primary-600 focus:ring-0 focus:ring-offset-0 cursor-pointer
                                               border-current"
                                    >
                                    <span x-text="perm.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    {{-- Collapsed summary --}}
                    <div
                        x-show="!isExpanded(group.module)"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                    >
                        <span class="text-xs text-gray-400 dark:text-gray-500 italic">
                            Click module name to expand
                            <template x-if="isModulePartiallySelected(group) || isModuleFullySelected(group)">
                                <span class="not-italic font-medium text-primary-600 dark:text-primary-400">
                                    (<span x-text="group.permissions.filter(p => selected.includes(p.name)).length"></span>
                                    permissions selected)
                                </span>
                            </template>
                        </span>
                    </div>

                </div>
            </div>
        </template>
    </div>

    {{-- ── FOOTER ─────────────────────────────────────────────────────── --}}
    <div class="rounded-b-xl border border-t-0 border-gray-200 dark:border-white/10
                bg-gray-50 dark:bg-gray-800/80 px-4 py-2.5
                flex items-center justify-between">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ $total }} permissions across {{ $grouped->count() }} modules.
            Changes apply when the role is saved.
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500">
            Hover a chip to see the full permission name
        </p>
    </div>

</div>
