<div
    class="erp-contas-caixa-locate-wrap"
    wire:ignore.self
    x-data
    x-on:keydown.escape.window="
        if (! $wire.showForm) {
            $event.preventDefault();
            $wire.handleContasCaixaEscape();
        }
    "
>
    @include('filament.components.erp.shared.cadastro-list-screen', [
        'pageClass' => 'erp-unidades',
        'searchFields' => [
            'codigo' => 'CÓDIGO',
            'nome' => 'DESCRIÇÃO',
        ],
        'uppercaseColumns' => 'nome',
        'wireKeyPrefix' => 'contas-caixa',
        'hint' => 'Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.',
    ])
</div>
