@php($m = $this->itemAjusteModal)
@include('filament.components.erp.desconto-item-modal', [
    'open' => $this->descontoModalOpen,
    'idPrefix' => 'erp-os-desconto',
    'confirmMethod' => 'confirmarItemAjuste',
    'closeMethod' => 'fecharModalDescontoItem',
    'descricao' => $m['descricao'],
    'base' => $m['baseRaw'],
    'qtd' => $m['qtdRaw'],
    'tipo' => $m['tipo'],
    'pctInicial' => $m['pct'],
    'reaisInicial' => $m['reais'],
    'modoReais' => $m['modoReais'],
    'lastField' => 'percentual',
])
