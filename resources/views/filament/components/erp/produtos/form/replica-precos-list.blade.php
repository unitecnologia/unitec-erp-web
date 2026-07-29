<div class="erp-prod-replica-precos">
    <div class="erp-prod-replica-precos__toolbar">
        <button
            type="button"
            class="erp-aviso-modal__btn erp-aviso-modal__btn--secondary erp-prod-replica-precos__mini"
            wire:click="toggleTodasReplicaPrecosEmpresas(true)"
        >
            Marcar todas
        </button>
        <button
            type="button"
            class="erp-aviso-modal__btn erp-aviso-modal__btn--secondary erp-prod-replica-precos__mini"
            wire:click="toggleTodasReplicaPrecosEmpresas(false)"
        >
            Desmarcar todas
        </button>
    </div>

    <div class="erp-prod-replica-precos__list">
        @forelse ($this->productReplicaPrecosOpcoes as $empresa)
            <label class="erp-prod-replica-precos__item">
                <input
                    type="checkbox"
                    value="{{ $empresa['id'] }}"
                    wire:model="productReplicaPrecosSelecionadas"
                >
                <span>
                    @if (! empty($empresa['codigo']))
                        <strong>{{ $empresa['codigo'] }}</strong> —
                    @endif
                    {{ $empresa['nome'] }}
                </span>
            </label>
        @empty
            <p class="erp-prod-replica-precos__empty">Nenhuma outra empresa disponível.</p>
        @endforelse
    </div>
</div>
