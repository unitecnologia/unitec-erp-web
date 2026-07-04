<div class="erp-ajusta-estoque-grupo" wire:ignore.self>
    <div class="erp-ajusta-estoque-grupo__filters">
        <label class="erp-ajusta-estoque-grupo__field">
            Selecione o Grupo
            <select wire:model.live="grupoFilter" class="erp-ajusta-estoque-grupo__select">
                <option value="todos">&lt;todos os grupos&gt;</option>
                @foreach ($this->gruposOptions as $nome => $label)
                    <option value="{{ $nome }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="erp-ajusta-estoque-grupo__field">
            Selecione a Marca
            <select wire:model.live="marcaFilter" class="erp-ajusta-estoque-grupo__select">
                <option value="todos">&lt;todas as marcas&gt;</option>
                @foreach ($this->marcasOptions as $nome => $label)
                    <option value="{{ $nome }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <fieldset class="erp-ajusta-estoque-grupo__radio-group">
            <legend>Com (Estoque)</legend>
            <label><input type="radio" wire:model.live="estoqueFilter" value="atual"> ATUAL</label>
            <label><input type="radio" wire:model.live="estoqueFilter" value="zerado"> ZERADO</label>
            <label><input type="radio" wire:model.live="estoqueFilter" value="negativo"> NEGATIVO</label>
        </fieldset>

        <fieldset class="erp-ajusta-estoque-grupo__radio-group">
            <legend>Status</legend>
            <label><input type="radio" wire:model.live="statusFilter" value="ativo"> ATIVO</label>
            <label><input type="radio" wire:model.live="statusFilter" value="inativo"> INATIVO</label>
        </fieldset>

        <button type="button" wire:click="pesquisar" class="erp-ajusta-estoque-grupo__search-btn" data-erp-key="F5">
            <span>🔍</span>
            <span><kbd>F5</kbd> | Pesquisar</span>
        </button>
    </div>

    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
