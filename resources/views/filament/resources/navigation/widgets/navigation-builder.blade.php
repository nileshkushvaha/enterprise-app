<x-filament-widgets::widget class="fi-wi-navigation-builder">
    @if ($record && $record->exists)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-y-1 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Menu Builder
                </h3>
                <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                    Add and arrange items for the <strong>{{ $record->name }}</strong> menu. Drag items to reorder or nest them.
                </p>
            </div>
            <div class="fi-section-content px-6 pb-6">
                @livewire('navigation.menu-builder', ['navigationId' => $record->id], key('nav-builder-' . $record->id))
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
