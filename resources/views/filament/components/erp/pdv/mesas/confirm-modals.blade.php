@if ($this->activeModal === 'mesa_parcial_confirmar' || $this->activeModal === 'mesa_reabrir')
    <?php $mesaConfirmar = \App\Support\Erp\Pdv\PdvMesaSessao::atual(); ?>
    <?php $mesaConfirmarRotulo = $mesaConfirmar ? \App\Support\Erp\Pdv\PdvMesaService::rotulo($mesaConfirmar['numero']) : 'mesa'; ?>

    @if ($this->activeModal === 'mesa_parcial_confirmar')
        @include('pdvui::modals.partials.confirm', [
            'titleId' => 'erp-pdv-mesa-parcial-title',
            'title' => 'Pré-conta · '.$mesaConfirmarRotulo,
            'message' => 'A pré-conta foi impressa corretamente? Com "Sim", a mesa fica aguardando fechamento e os itens são bloqueados.',
            'confirmAction' => 'confirmarParcialMesa',
            'cancelAction' => 'cancelarParcialMesa',
            'confirmId' => 'erp-pdv-mesa-parcial-sim',
            'cancelId' => 'erp-pdv-mesa-parcial-nao',
        ])
    @else
        @include('pdvui::modals.partials.confirm', [
            'titleId' => 'erp-pdv-mesa-reabrir-title',
            'title' => 'Reabrir · '.$mesaConfirmarRotulo,
            'message' => 'Reabrir a mesa? Ela volta a receber lançamentos e a pré-conta impressa deixa de valer.',
            'confirmAction' => 'confirmarReabrirMesa',
            'cancelAction' => 'cancelReabrirMesa',
            'confirmId' => 'erp-pdv-mesa-reabrir-sim',
            'cancelId' => 'erp-pdv-mesa-reabrir-nao',
        ])
    @endif
@endif
