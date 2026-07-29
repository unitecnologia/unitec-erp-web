<div class="erp-logistica" wire:ignore.self>
    <div class="erp-logistica__tabs-wrap">
        <div class="erp-logistica__tabs">
            @foreach ($this->modosDisponiveis() as $value => $label)
                <button
                    type="button"
                    wire:click="setModo('{{ $value }}')"
                    @class([
                        'erp-logistica__tab',
                        'erp-logistica__tab--active' => $this->modo === $value,
                        'erp-logistica__tab--' . $value => true,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="erp-logistica__locate">
        <span class="erp-logistica__locate-label">{{ $this->screenTitle() }}</span>
        <p class="erp-logistica__hint">Selecione uma linha e pressione F3 ou use os botões abaixo para operar.</p>
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
