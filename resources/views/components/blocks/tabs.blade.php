<!-- Tabs Block -->
<section class="tabs-block py-12">
    <div class="container">
        <div x-data="{ activeTab: 0 }" class="max-w-4xl mx-auto">
            <!-- Tab Buttons -->
            <div class="flex border-b gap-2 flex-wrap">
                @foreach($items ?? [] as $index => $item)
                    <button @click="activeTab = {{ $index }}" 
                            :class="{ 'border-b-2 border-blue-500 font-semibold': activeTab === {{ $index }} }"
                            class="px-4 py-3 text-gray-700 hover:text-blue-500 transition">
                         {{ $item['title'] ?? $item['label'] ?? 'Tab ' . ($index + 1) }}
                    </button>
                @endforeach
            </div>
            
            <!-- Tab Content -->
            <div class="py-8">
                @foreach($items ?? [] as $index => $item)
                    <div x-show="activeTab === {{ $index }}" x-cloak>
                        @if(($item['content'] ?? $item['tab_content'] ?? false))
                            {!! nl2br(e($item['content'] ?? $item['tab_content'])) !!}
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
