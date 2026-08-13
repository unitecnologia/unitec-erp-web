<div class="erp-ajusta-estoque-grupo" wire:ignore.self>
    <div class="erp-ajusta-estoque-grupo__filters">
        <div class="erp-ajusta-estoque-grupo__filters-row">
            <label class="erp-ajusta-estoque-grupo__field">
                <span class="erp-ajusta-estoque-grupo__label">Selecione o Grupo</span>
                <select wire:model.live="grupoFilter" class="erp-ajusta-estoque-grupo__select">
                    <option value="todos">&lt;todos os grupos&gt;</option>
                    @foreach ($this->gruposOptions as $nome => $label)
                        <option value="{{ $nome }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-ajusta-estoque-grupo__field">
                <span class="erp-ajusta-estoque-grupo__label">Selecione a Marca</span>
                <select wire:model.live="marcaFilter" class="erp-ajusta-estoque-grupo__select">
                    <option value="todos">&lt;todas as marcas&gt;</option>
                    @foreach ($this->marcasOptions as $nome => $label)
                        <option value="{{ $nome }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="erp-ajusta-estoque-grupo__seg-group" role="group" aria-label="Com (Estoque)">
                <span class="erp-ajusta-estoque-grupo__label">Com (Estoque)</span>
                <div class="erp-ajusta-estoque-grupo__seg" role="radiogroup">
                    <label @class(['erp-ajusta-estoque-grupo__chip', 'is-active' => $this->estoqueFilter === 'atual'])>
                        <input type="radio" wire:model.live="estoqueFilter" value="atual">
                        <span>ATUAL</span>
                    </label>
                    <label @class(['erp-ajusta-estoque-grupo__chip', 'is-active' => $this->estoqueFilter === 'negativo'])>
                        <input type="radio" wire:model.live="estoqueFilter" value="negativo">
                        <span>NEGATIVO</span>
                    </label>
                    <label @class(['erp-ajusta-estoque-grupo__chip', 'is-active' => $this->estoqueFilter === 'zerado'])>
                        <input type="radio" wire:model.live="estoqueFilter" value="zerado">
                        <span>ZERADO</span>
                    </label>
                </div>
            </div>

            <div class="erp-ajusta-estoque-grupo__seg-group" role="group" aria-label="Status">
                <span class="erp-ajusta-estoque-grupo__label">Status</span>
                <div class="erp-ajusta-estoque-grupo__seg" role="radiogroup">
                    <label @class(['erp-ajusta-estoque-grupo__chip', 'is-active' => $this->statusFilter === 'ativo'])>
                        <input type="radio" wire:model.live="statusFilter" value="ativo">
                        <span>ATIVO</span>
                    </label>
                    <label @class(['erp-ajusta-estoque-grupo__chip', 'is-active' => $this->statusFilter === 'inativo'])>
                        <input type="radio" wire:model.live="statusFilter" value="inativo">
                        <span>INATIVO</span>
                    </label>
                </div>
            </div>

            <div class="erp-ajusta-estoque-grupo__toolbar-actions">
                <button
                    type="button"
                    wire:click="pesquisar"
                    class="erp-ajusta-estoque-grupo__btn erp-ajusta-estoque-grupo__btn--search"
                    data-erp-key="F5"
                    title="Pesquisar (F5)"
                >
                    <svg class="erp-ajusta-estoque-grupo__btn-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <circle cx="8.5" cy="8.5" r="5.25" stroke="currentColor" stroke-width="1.75"/>
                        <path d="M12.5 12.5L16.25 16.25" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                    </svg>
                    <span class="erp-ajusta-estoque-grupo__btn-label"><kbd>F5</kbd> | Pesquisar</span>
                </button>
                <button
                    type="button"
                    wire:click="limparFiltros"
                    class="erp-ajusta-estoque-grupo__btn erp-ajusta-estoque-grupo__btn--ghost"
                    title="Limpar filtros"
                >
                    Limpar
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
