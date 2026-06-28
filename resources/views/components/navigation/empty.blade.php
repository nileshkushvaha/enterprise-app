{{--
    Empty navigation — renders nothing when no menu is found or menu has no items.
    An HTML comment is emitted in non-production environments for debugging.
--}}
@unless(app()->isProduction())
<!-- Navigation "{{ $location ?? 'unknown' }}" returned empty or was not found -->
@endunless
