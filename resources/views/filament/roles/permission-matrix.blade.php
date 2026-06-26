{{--
    Permission Matrix Component
    ───────────────────────────
    Renders a responsive permission matrix table with Alpine.js state management.
    State is synced to the parent Livewire page via $wire.selectedPermissions.

    Variables injected via viewData():
        $allPermissions  - array<string>  all permission names (e.g. "View:Country")
        $modules         - array<string>  distinct modules    (e.g. "Country")
        $actions         - array<string>  distinct actions    (e.g. "View")
--}}

@php
    /** @var array<string> $allPermissions */
    /** @var array<string> $modules */
    /** @var array<string> $actions */

    // Build a lookup set: "Action:Module" => true (only if permission actually exists in DB)
    $existingSet = array_flip($allPermissions);

    // Short display labels for long action names
    $actionLabels = [
        'View'            => 'View',
        'ViewAny'         => 'View Any',
        'Create'          => 'Create',
        'Update'          => 'Update',
        'Delete'          => 'Delete',
        'DeleteAny'       => 'Del Any',
        'ForceDelete'     => 'F.Delete',
        'ForceDeleteAny'  => 'F.Del Any',
        'Restore'         => 'Restore',
        'RestoreAny'      => 'Rest Any',
        'Replicate'       => 'Replicate',
        'Reorder'         => 'Reorder',
    ];
@endphp

<div
    x-data="{
        selected: $wire.entangle('selectedPermissions'),

        isChecked(permName) {
            return this.selected.includes(permName);
        },

        toggle(permName) {
            const idx = this.selected.indexOf(permName);
            if (idx >= 0) {
                this.selected.splice(idx, 1);
            } else {
                this.selected.push(permName);
            }
        },

        selectModule(module, existingPerms) {
            existingPerms.forEach(p => {
                if (!this.selected.includes(p)) this.selected.push(p);
            });
        },

        clearModule(module, existingPerms) {
            existingPerms.forEach(p => {
                const idx = this.selected.indexOf(p);
                if (idx >= 0) this.selected.splice(idx, 1);
            });
        },

        selectAll() {
            this.selected = @js($allPermissions);
        },

        clearAll() {
            this.selected = [];
        },

        modulePerms(module) {
            return @js($allPermissions).filter(p => p.endsWith(':' + module));
        },

        isModuleFullySelected(module) {
            const perms = this.modulePerms(module);
            return perms.length > 0 && perms.every(p => this.selected.includes(p));
        },

        isModulePartiallySelected(module) {
            const perms = this.modulePerms(module);
            return perms.some(p => this.selected.includes(p)) && !this.isModuleFullySelected(module);
        }
    }"
    class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700"
>

    {{-- Global Toolbar --}}
    <div class="flex items-center justify-between gap-4 px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
            <span x-text="selected.length"></span>
            of {{ count($allPermissions) }} permissions selected
        </div>
        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="selectAll()"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium
                       bg-primary-600 text-white hover:bg-primary-700 transition-colors"
            >
                <x-heroicon-o-check-circle class="h-3.5 w-3.5" />
                Select All
            </button>
            <button
                type="button"
                @click="clearAll()"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium
                       bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200
                       hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
            >
                <x-heroicon-o-x-circle class="h-3.5 w-3.5" />
                Clear All
            </button>
        </div>
    </div>

    {{-- Matrix Table --}}
    <table class="w-full min-w-max text-sm border-collapse">
        {{-- Header: Actions --}}
        <thead>
            <tr class="bg-gray-100 dark:bg-gray-800">
                <th class="sticky left-0 z-10 bg-gray-100 dark:bg-gray-800 text-left px-4 py-3
                           font-semibold text-gray-600 dark:text-gray-300 min-w-[140px] border-b border-r
                           border-gray-200 dark:border-gray-700">
                    Module
                </th>
                <th class="px-2 py-3 text-center font-semibold text-gray-600 dark:text-gray-300 min-w-[56px]
                           border-b border-gray-200 dark:border-gray-700 text-xs">
                    All
                </th>
                @foreach ($actions as $action)
                    <th class="px-2 py-3 text-center font-semibold text-gray-600 dark:text-gray-300
                               min-w-[76px] border-b border-gray-200 dark:border-gray-700 text-xs whitespace-nowrap">
                        {{ $actionLabels[$action] ?? $action }}
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
            @foreach ($modules as $module)
                @php
                    $modulePermNames = collect($allPermissions)->filter(fn($p) => str_ends_with($p, ':' . $module))->values()->toArray();
                @endphp
                <tr class="hover:bg-primary-50/40 dark:hover:bg-primary-900/10 transition-colors">

                    {{-- Module name cell --}}
                    <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-4 py-3 font-medium
                               text-gray-800 dark:text-gray-200 border-r border-gray-200 dark:border-gray-700
                               hover:bg-primary-50/40 dark:hover:bg-primary-900/10">
                        {{ $module }}
                    </td>

                    {{-- Row "select all" toggle --}}
                    <td class="px-2 py-3 text-center">
                        <div class="flex items-center justify-center">
                            <input
                                type="checkbox"
                                :checked="isModuleFullySelected('{{ $module }}')"
                                :indeterminate="isModulePartiallySelected('{{ $module }}')"
                                @change="
                                    if (isModuleFullySelected('{{ $module }}')) {
                                        clearModule('{{ $module }}', modulePerms('{{ $module }}'));
                                    } else {
                                        selectModule('{{ $module }}', modulePerms('{{ $module }}'));
                                    }
                                "
                                class="rounded border-gray-300 text-primary-600 shadow-sm
                                       focus:ring-primary-500 cursor-pointer h-4 w-4"
                            >
                        </div>
                    </td>

                    {{-- Permission cells --}}
                    @foreach ($actions as $action)
                        @php
                            $permName = $action . ':' . $module;
                            $exists   = isset($existingSet[$permName]);
                        @endphp
                        <td class="px-2 py-3 text-center">
                            @if ($exists)
                                <div class="flex items-center justify-center">
                                    <input
                                        type="checkbox"
                                        :checked="isChecked('{{ $permName }}')"
                                        @change="toggle('{{ $permName }}')"
                                        class="rounded border-gray-300 text-primary-600 shadow-sm
                                               focus:ring-primary-500 cursor-pointer h-4 w-4"
                                        title="{{ $permName }}"
                                    >
                                </div>
                            @else
                                <span class="text-gray-300 dark:text-gray-600 select-none text-base">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Footer summary --}}
    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-500 dark:text-gray-400">
        {{ count($allPermissions) }} total permissions across {{ count($modules) }} modules.
        Changes are applied when you save the role.
    </div>
</div>
