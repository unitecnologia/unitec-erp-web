@php
    use App\Models\Entrega;
    $columns = $this->kanbanColumns();
    $labels = Entrega::statusLabels();
@endphp

<div class="erp-logistica erp-logistica-central" wire:poll.15s>
    <div class="erp-logistica__header">
        <h2 class="erp-logistica__title">Central de Entregas</h2>
        <div class="erp-logistica__header-actions">
            <button type="button" wire:click="refreshKanban" class="erp-logistica-actions__btn" data-erp-key="F5">
                <span class="erp-logistica-actions__label"><kbd>F5</kbd> Atualizar</span>
            </button>
            <button type="button" wire:click="closeScreen" class="erp-logistica-actions__btn erp-logistica-actions__btn--close">
                <span class="erp-logistica-actions__label">Fechar</span>
            </button>
        </div>
    </div>

    <div class="erp-logistica-kanban">
        @foreach (Entrega::kanbanStatuses() as $status)
            @php
                $cards = $columns[$status] ?? collect();
            @endphp
            <div class="erp-logistica-kanban__col erp-logistica-kanban__col--{{ $status }}">
                <div class="erp-logistica-kanban__col-head">
                    <span class="erp-logistica-kanban__col-title">{{ $labels[$status] ?? $status }}</span>
                    <span class="erp-logistica-kanban__col-count">{{ $cards->count() }}</span>
                </div>
                <div class="erp-logistica-kanban__col-body">
                    @forelse ($cards as $entrega)
                        <div class="erp-logistica-card" wire:key="entrega-card-{{ $entrega->id }}">
                            <div class="erp-logistica-card__top">
                                <strong>#{{ $entrega->numero }}</strong>
                                <span>Venda {{ $entrega->venda?->numero ?? '—' }}</span>
                            </div>
                            <div class="erp-logistica-card__cliente">{{ $entrega->cliente_nome ?? 'Cliente' }}</div>
                            <div class="erp-logistica-card__endereco">{{ $entrega->endereco_bairro ?? '—' }} · {{ $entrega->endereco_cidade ?? '—' }}</div>
                            <div class="erp-logistica-card__meta">
                                {{ match ($entrega->origem) {
                                    Entrega::ORIGEM_PDV => 'PDV',
                                    Entrega::ORIGEM_MONITOR => 'Monitor',
                                    default => 'ERP',
                                } }}
                            </div>
                            <button
                                type="button"
                                class="erp-logistica-card__btn"
                                wire:click="avancarEntrega({{ $entrega->id }})"
                            >
                                Avançar →
                            </button>
                        </div>
                    @empty
                        <div class="erp-logistica-kanban__empty">Nenhuma entrega</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
