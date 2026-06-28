{{--
    Standard (horizontal) navigation — suitable for header menus.
    Variables: $tree (NavigationTree), $navLabel (string)

    Usage: <x-navigation location="header" />
--}}
<nav aria-label="{{ $navLabel }}" id="{{ $tree->slug }}-nav">
    <ul
        class="flex items-center gap-0.5"
        role="list"
        aria-label="{{ $navLabel }}"
    >
        @foreach($tree->nodes as $node)
            @include('components.navigation._node', [
                'node'  => $node,
                'depth' => 0,
                'tree'  => $tree,
            ])
        @endforeach
    </ul>
</nav>
