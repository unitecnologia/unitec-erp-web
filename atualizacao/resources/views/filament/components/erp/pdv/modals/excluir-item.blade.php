@if ($this->activeModal === 'excluir_item')
    @include('filament.components.erp.pdv.modals.partials.confirm', [
        'titleId' => 'erp-pdv-excluir-title',
        'title' => 'Confirmação',
        'message' => 'Deseja excluir o item?',
        'confirmAction' => 'confirmExcluirItemCupom',
        'cancelAction' => 'cancelExcluirItemCupom',
        'confirmId' => 'erp-pdv-excluir-sim',
        'cancelId' => 'erp-pdv-excluir-nao',
    ])
@endif
