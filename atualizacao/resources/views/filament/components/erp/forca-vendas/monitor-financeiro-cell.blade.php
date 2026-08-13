@php
    /** @var \App\Models\ForcaVendasOrder $record */
    $record = $getRecord();
    $ehFinanceiro = $record->situacao === \App\Models\ForcaVendasOrder::SITUACAO_FINANCEIRO;
    $liberado = ! empty(($record->payload['financeiro_liberado'] ?? false));
@endphp

@if ($ehFinanceiro)
    <button
        type="button"
        class="fi-badge fi-size-sm erp-fv-mon__fin-btn"
        wire:click.stop="abrirLiberacaoFinanceira({{ (int) $record->getKey() }})"
        wire:key="fv-fin-{{ $record->getKey() }}"
        @click.stop
        title="Abrir liberação financeira"
    >
        <span class="fi-badge-label">Financeiro</span>
    </button>
@elseif ($liberado)
    <span class="fi-badge fi-size-sm fi-color-success erp-fv-mon__fin-ok">
        <span class="fi-badge-label">Liberado</span>
    </span>
@else
    <span class="erp-fv-mon__fin-empty">—</span>
@endif
