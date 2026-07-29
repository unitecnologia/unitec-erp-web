<div
    class="erp-contas-caixa-locate-wrap"
    wire:ignore.self
    x-data
    x-on:keydown.escape.window="
        $event.preventDefault();
        $wire.closeScreen();
    "
>
    @include('filament.components.erp.shared.cadastro-list-screen', [
        'pageClass' => 'erp-unidades',
        'searchFields' => [
            'codigo' => 'CÓDIGO',
            'descricao' => 'DESCRIÇÃO',
        ],
        'uppercaseColumns' => 'descricao',
        'wireKeyPrefix' => 'planos',
        'hint' => 'Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.',
    ])
</div>
