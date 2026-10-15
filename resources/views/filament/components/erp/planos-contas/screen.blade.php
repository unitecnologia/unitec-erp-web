<div
    class="erp-contas-caixa-locate-wrap"
    wire:ignore.self
    x-data
    x-on:keydown.escape.window="
        if (! $wire.showForm && ! $wire.deleteConfirmOpen) {
            $event.preventDefault();
            $wire.closeScreen();
        }
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
        'hint' => 'Enter pesquisa · setas navegam na lista · F2 novo · F3 alterar · F4 excluir',
    ])
</div>
