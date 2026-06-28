{{--
    Footer navigation — renders as a flat list or multi-column if root items have children.
    Variables: $tree (NavigationTree), $navLabel (string)

    Usage: <x-navigation location="footer" />
    Usage: <x-navigation location="footer-company" />
--}}
@php
    // Multi-column if root items have children that act as column headings
    $hasColumns = collect($tree->nodes)->some(fn ($n) => $n->hasChildren());
@endphp

<nav aria-label="{{ $navLabel }}" id="{{ $tree->slug }}-nav">
    @if($hasColumns)
        {{-- Multi-column layout: each root node is a column with a heading + child links --}}
        <div class="grid gap-8" style="grid-template-columns: repeat({{ min(count($tree->nodes), 4) }}, minmax(0, 1fr));">
            @foreach($tree->nodes as $node)
                <div>
                    {{-- Column heading --}}
                    @if(!$node->link->isEmpty())
                    <a
                        href="{{ $node->link->url }}"
                        class="text-sm font-semibold text-slate-200 hover:text-white transition-colors"
                        aria-current="{{ $node->isActive ? 'page' : 'false' }}"
                    >{{ $node->label }}</a>
                    @else
                    <p class="text-sm font-semibold text-slate-200">{{ $node->label }}</p>
                    @endif

                    {{-- Column links --}}
                    @if($node->hasChildren())
                    <ul class="mt-3 space-y-1" role="list" aria-label="{{ $node->label }} links">
                        @foreach($node->children as $child)
                            @include('components.navigation._node-footer', ['node' => $child])
                        @endforeach
                    </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        {{-- Flat list --}}
        <ul class="space-y-1" role="list">
            @foreach($tree->nodes as $node)
                @include('components.navigation._node-footer', ['node' => $node])
            @endforeach
        </ul>
    @endif
</nav>
