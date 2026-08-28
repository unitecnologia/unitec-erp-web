<div
    class="erp-pdv erp-pdv--click-guard"
    data-caixa-aberto="{{ $this->caixaAberto ? '1' : '0' }}"
    data-exibe-f4="1"
    data-permite-desconto-item="{{ $this->pdvPermitirDescontoItem ? '1' : '0' }}"
    data-som-ativo="{{ $this->pdvSomAtivo ? '1' : '0' }}"
    data-exibe-mesas="{{ $this->pdvExibeMesas ? '1' : '0' }}"
    data-caixa-rapido="{{ $this->pdvCaixaRapido ? '1' : '0' }}"
    data-ler-peso-balanca="{{ $this->pdvLerPesoBalanca ? '1' : '0' }}"
    data-balanca-settings='@json($this->pdvBalancaSettings)'
    data-busca-balanca-barras="{{ $this->pdvBuscaBalancaBarras ? '1' : '0' }}"
    data-usa-tef="{{ $this->pdvUsaTef ? '1' : '0' }}"
>
    {{-- Fonte única: mesmas telas/modais do PDV offline. --}}
    @include('pdvui::screen')
</div>

@include('filament.components.erp.form-scripts')
