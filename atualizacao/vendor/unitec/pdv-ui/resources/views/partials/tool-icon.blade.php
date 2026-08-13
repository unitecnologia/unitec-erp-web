{{-- Ícone 3D da toolbar do PDV. Uso: @include('pdvui::partials.tool-icon', ['name' => 'exit']) --}}
@php
    $name = $name ?? 'options';
@endphp
<span class="erp-pdv__tool-icon erp-pdv__tool-icon--{{ $name }}" aria-hidden="true">
    <span class="erp-pdv__tool-icon-orb"></span>
    <svg class="erp-pdv__tool-icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        @switch($name)
            @case('exit')
                <path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                <path d="M14 16l4-4-4-4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M10 12h8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                @break
            @case('import')
                <path d="M12 4v11" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                <path d="M8 11l4 4 4-4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M5 19h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                @break
            @case('cancel')
                <path d="M7 7l10 10M17 7L7 17" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                @break
            @case('finish')
                <path d="M5 12.5l4.5 4.5L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                @break
            @case('pause')
                <path d="M8.5 6v12M15.5 6v12" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                @break
            @case('resumo')
                <rect x="5" y="3.5" width="14" height="17" rx="2" stroke="currentColor" stroke-width="2"/>
                <path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                @break
            @case('sangria')
                <path d="M12 4v10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                <path d="M8.5 10.5L12 14l3.5-3.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M7 18h10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                <circle cx="12" cy="19.5" r="1.2" fill="currentColor"/>
                @break
            @case('suprimento')
                <path d="M12 19V8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                <path d="M8.5 11.5L12 8l3.5 3.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M7 5h10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                @break
            @case('cliente')
                <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="2"/>
                <path d="M5.5 19c1.2-3.2 3.4-4.8 6.5-4.8S17.8 15.8 19 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                @break
            @case('produto')
                <path d="M4.5 8.5L12 4.5l7.5 4V16.5L12 20.5l-7.5-4V8.5z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
                <path d="M12 12v8.5M4.5 8.5L12 12l7.5-3.5" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
                @break
            @default
                <path d="M5 7h14M5 12h14M5 17h14" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"/>
        @endswitch
    </svg>
</span>
