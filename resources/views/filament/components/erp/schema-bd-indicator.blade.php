@php
    $bdStatus = app(\App\Support\Erp\Database\PendingMigrationsGuard::class)->statusForUi();
    $bdClass = $class ?? 'erp-schema-bd';
    $bdState = (string) ($bdStatus['state'] ?? (($bdStatus['ok'] ?? false) ? 'ok' : 'pending'));
@endphp
<span
    class="{{ $bdClass }} {{ $bdClass }}--{{ $bdState }}"
    title="{{ $bdStatus['tooltip'] }}"
    aria-label="{{ $bdStatus['label'] }}. {{ $bdStatus['tooltip'] }}"
>{{ $bdStatus['label'] }}</span>
