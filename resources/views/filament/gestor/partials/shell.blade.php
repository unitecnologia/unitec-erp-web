@php
    $tema = $this->gestorTema ?? 'light';
@endphp
<div class="gestor-shell" data-theme="{{ $tema }}">
    <div class="gestor-shell__inner">
        {{ $slot }}
    </div>

    @include('filament.gestor.partials.bottom-nav')
</div>
