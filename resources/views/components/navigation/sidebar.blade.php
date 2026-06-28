{{--
    Sidebar / accordion navigation — vertical tree with expandable children.
    Variables: $tree (NavigationTree), $navLabel (string)

    Usage: <x-navigation location="sidebar" />
--}}
<nav aria-label="{{ $navLabel }}" id="{{ $tree->slug }}-nav">
    <ul class="space-y-0.5" role="list">
        @foreach($tree->nodes as $node)
            @include('components.navigation._node-sidebar', [
                'node'  => $node,
                'depth' => 0,
                'tree'  => $tree,
            ])
        @endforeach
    </ul>
</nav>
