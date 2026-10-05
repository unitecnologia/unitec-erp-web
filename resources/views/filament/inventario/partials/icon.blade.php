@php
    $name = $name ?? '';
@endphp
<svg class="inv-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @if ($name === 'back')
        <path d="M15 18l-6-6 6-6"/>
    @elseif ($name === 'search')
        <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
    @elseif ($name === 'dots')
        <circle cx="12" cy="5" r="1" fill="currentColor" stroke="none"/>
        <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/>
        <circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/>
    @elseif ($name === 'logout')
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>
    @elseif ($name === 'download')
        <path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M5 21h14"/>
    @elseif ($name === 'play')
        <path d="M8 5v14l11-7-11-7z"/>
    @elseif ($name === 'plus')
        <path d="M12 5v14M5 12h14"/>
    @elseif ($name === 'check')
        <path d="M5 12.5l4.2 4.2L19 7.5"/>
    @elseif ($name === 'clock')
        <circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/>
    @elseif ($name === 'lock')
        <rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>
    @endif
</svg>
