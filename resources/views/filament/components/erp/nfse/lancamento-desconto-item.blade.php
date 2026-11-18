@php($m = $this->nfseServicoAjusteModal)
@include('filament.components.erp.desconto-item-modal', [
    'open' => $this->nfseDescontoModalOpen,
    'idPrefix' => 'erp-nfse-desconto',
    'confirmMethod' => 'confirmarNfseServicoAjuste',
    'closeMethod' => 'fecharNfseModalDescontoItem',
    'descricao' => $m['descricao'],
    'base' => $m['baseRaw'],
    'qtd' => $m['qtdRaw'],
    'tipo' => $m['tipo'],
    'pctInicial' => $m['pct'],
    'reaisInicial' => $m['reais'],
    'modoReais' => $m['modoReais'],
    'lastField' => 'percentual',
])
