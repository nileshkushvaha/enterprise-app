{{-- Tabs Block --}}
<section class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div x-data="{ activeTab: 0 }">
            <div class="flex gap-1 border-b border-slate-200 overflow-x-auto">
                @foreach($items ?? [] as $index => $item)
                    <button
                        @click="activeTab = {{ $index }}"
                        :class="activeTab === {{ $index }}
                            ? 'border-b-2 border-indigo-600 text-indigo-600 font-semibold'
                            : 'text-slate-500 hover:text-slate-700'"
                        class="px-4 py-3 text-sm transition-colors whitespace-nowrap flex-shrink-0 -mb-px">
                        {{ $item['title'] ?? $item['label'] ?? 'Tab ' . ($index + 1) }}
                    </button>
                @endforeach
            </div>
            <div class="pt-8">
                @foreach($items ?? [] as $index => $item)
                    <div x-show="activeTab === {{ $index }}" x-cloak
                         class="prose prose-slate prose-sm max-w-none prose-headings:text-slate-900 prose-p:text-slate-600 prose-a:text-indigo-600 prose-strong:text-slate-800">
                        @if($item['content'] ?? $item['tab_content'] ?? false)
                            {!! $item['content'] ?? $item['tab_content'] !!}
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
