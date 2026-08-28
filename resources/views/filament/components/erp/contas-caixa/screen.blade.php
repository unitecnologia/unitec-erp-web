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
        'pageClass' => 'erp-contas-caixa',
        'searchFields' => [
            'codigo' => 'Código',
            'nome' => 'Descrição',
        ],
        'uppercaseColumns' => 'nome',
        'wireKeyPrefix' => 'contas-caixa',
        'hint' => 'Enter pesquisa · setas navegam na lista · F2 novo · F3 alterar',
    ])
</div>
