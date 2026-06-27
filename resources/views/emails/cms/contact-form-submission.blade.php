@php
    $title = $payload['page_title'] ?? 'Unknown page';
    $submittedAt = $payload['submitted_at'] ?? now()->toIso8601String();
@endphp

<h2>New contact form submission</h2>

<p><strong>Page:</strong> {{ $title }} ({{ $payload['page_slug'] ?? 'n/a' }})</p>
<p><strong>Submitted at:</strong> {{ $submittedAt }}</p>
<p><strong>IP:</strong> {{ $payload['ip'] ?? 'n/a' }}</p>

<hr>

<h3>Submitted Fields</h3>
<ul>
    @foreach(($payload['fields'] ?? []) as $key => $value)
        <li>
            <strong>{{ $payload['field_labels'][$key] ?? $key }}:</strong>
            {{ is_scalar($value) ? (string) $value : json_encode($value) }}
        </li>
    @endforeach
</ul>

