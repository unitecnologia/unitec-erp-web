@if ($this->activeModal === 'sair')
    @include('filament.components.erp.pdv.modals.partials.confirm', [
        'titleId' => 'erp-pdv-sair-title',
        'title' => 'Sair do PDV',
        'message' => 'Confirma sair do PDV?',
        'confirmAction' => 'confirmSairPdv',
        'cancelAction' => 'closePdvModal',
        'confirmId' => 'erp-pdv-sair-sim',
        'cancelId' => 'erp-pdv-sair-nao',
    ])
@endif
