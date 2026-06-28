{{--
    Mobile navigation — accordion drawer.
    Variables: $tree (NavigationTree), $navLabel (string)

    Usage: <x-navigation location="mobile" />

    Integrate with your mobile menu toggle by wrapping in an Alpine x-show div:
        <div x-show="mobileOpen" x-collapse>
            <x-navigation location="mobile" />
        </div>
--}}
<nav
    aria-label="{{ $navLabel }}"
    id="{{ $tree->slug }}-mobile-nav"
>
    <ul
        class="divide-y divide-white/[0.04]"
        role="list"
    >
        @foreach($tree->nodes as $node)
            @include('components.navigation._node-mobile', [
                'node'  => $node,
                'depth' => 0,
                'tree'  => $tree,
            ])
        @endforeach
    </ul>
</nav>
