@php($m = $this->nfeItemAjusteModal)
@include('filament.components.erp.desconto-item-modal', [
    'open' => $this->nfeDescontoModalOpen,
    'idPrefix' => 'erp-nfe-desconto',
    'confirmMethod' => 'confirmarNfeItemAjuste',
    'closeMethod' => 'fecharNfeModalDescontoItem',
    'descricao' => $m['descricao'],
    'base' => $m['baseRaw'],
    'qtd' => $m['qtdRaw'],
    'tipo' => $m['tipo'],
    'pctInicial' => $m['pct'],
    'reaisInicial' => $m['reais'],
    'modoReais' => $m['modoReais'],
    'lastField' => 'percentual',
])
