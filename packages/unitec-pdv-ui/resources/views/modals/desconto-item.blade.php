@php($m = $this->descontoItemModal)
@include('filament.components.erp.desconto-item-modal', [
    'open' => $this->activeModal === 'desconto_item',
    'idPrefix' => 'erp-pdv-desconto',
    'confirmMethod' => 'confirmDescontoItem',
    'closeMethod' => 'closePdvModal',
    'descricao' => $m['descricao'],
    'base' => $m['baseRaw'],
    'qtd' => $m['qtdRaw'],
    'tipo' => $m['tipo'],
    'pctInicial' => $m['pct'],
    'reaisInicial' => $m['reais'],
    'modoReais' => $m['modoReais'],
    'lastField' => 'percentual',
])
