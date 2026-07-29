@php
    use App\Models\PdvVendaNfce;

    $record = $getRecord();
    $podeMarcar = $this->statusFilter === PdvVendaNfce::TAB_CONTINGENCIA
        && $record->status === PdvVendaNfce::STATUS_CONTINGENCIA
        && ! $record->simulada;
@endphp

@if ($podeMarcar)
    <input
        type="checkbox"
        class="erp-nfce__transmit-check"
        value="{{ $record->getKey() }}"
        @checked(in_array((string) $record->getKey(), $this->nfceSelecionadosTransmitir, true))
        wire:click.prevent.stop="toggleNfceTransmitirSelecionado({{ $record->getKey() }})"
        wire:key="nfce-transmit-sel-{{ $record->getKey() }}-{{ in_array((string) $record->getKey(), $this->nfceSelecionadosTransmitir, true) ? '1' : '0' }}"
        title="Marcar para transmitir"
        @click.stop
    >
@endif
