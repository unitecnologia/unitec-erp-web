<div
    x-data
    x-on:keydown.window="
        if (! $wire.reabrirConfirmOpen) return;
        if ($event.key === 'Escape') { $event.preventDefault(); $wire.cancelReabrirDevolucao(); }
        if ($event.key === 'Enter') { $event.preventDefault(); $wire.confirmReabrirDevolucao(); }
    "
>
    @include('filament.components.erp.aviso-modal', [
        'open' => $this->reabrirConfirmOpen,
        'tone' => 'warning',
        'titleId' => 'erp-devcompra-reabrir-title',
        'title' => 'Reabrir devolução',
        'lines' => [
            'Reabrir esta devolução?',
            'O estoque baixado na finalização será estornado.',
        ],
        'primaryLabel' => 'Sim',
        'primaryAction' => 'confirmReabrirDevolucao',
        'secondaryLabel' => 'Não',
        'secondaryAction' => 'cancelReabrirDevolucao',
        'escapeAction' => 'cancelReabrirDevolucao',
        'backdropAction' => 'cancelReabrirDevolucao',
    ])
</div>
