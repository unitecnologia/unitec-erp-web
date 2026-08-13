<div class="erp-os-panel">
    <h3 class="erp-os-panel__title">Dados da OS</h3>

    <div class="erp-os-form-row">
        <div class="erp-os-form-group erp-os-form-group--xs">
            <label class="erp-os-form-label" for="os-numero">Número</label>
            <input id="os-numero" type="text" readonly value="{{ $this->osNumeroDisplay() }}" class="erp-os-form-input">
        </div>

        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-cliente">Cliente | Razão Social ou CNPJ</label>
            <div class="erp-os-cliente-field">
                <input
                    id="os-cliente"
                    type="text"
                    wire:model.live.debounce.250ms="clienteSearch"
                    wire:focus="openClienteLookup"
                    wire:keydown.arrow-up.prevent="moveClienteSelection(-1)"
                    wire:keydown.arrow-down.prevent="moveClienteSelection(1)"
                    wire:keydown.enter.prevent="handleClienteEnter"
                    wire:keydown.escape.prevent="closeClienteLookup"
                    wire:blur="confirmClienteSelectionOnBlur"
                    @disabled($readOnly)
                    class="erp-os-form-input"
                    autocomplete="off"
                    placeholder="Digite razão social, fantasia ou CNPJ"
                >
                @if ($this->clienteLookupOpen && filled($this->clienteSearch))
                    @if ($this->clienteResults !== [])
                        @include('filament.components.erp.orcamentos.form.cliente-lookup')
                    @else
                        <div class="erp-orc-cliente-lookup erp-orc-cliente-lookup--empty">
                            Nenhum cliente encontrado.
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="erp-os-form-group erp-os-form-group--doc">
            <label class="erp-os-form-label" for="os-documento">Documento</label>
            <input id="os-documento" type="text" wire:model="documento" @disabled($readOnly) class="erp-os-form-input">
        </div>

        <div class="erp-os-form-group erp-os-form-group--phone">
            <label class="erp-os-form-label" for="os-fone1">Fone 1</label>
            <input id="os-fone1" type="text" wire:model="fone1" @disabled($readOnly) data-mask="phone" class="erp-os-form-input">
        </div>
    </div>

    <div class="erp-os-form-row">
        <div class="erp-os-form-group erp-os-form-group--atendente">
            <label class="erp-os-form-label" for="os-atendente">Atendente</label>
            <select id="os-atendente" wire:model="atendenteId" @disabled($readOnly) class="erp-os-form-select">
                <option value="">Selecione...</option>
                @foreach ($this->atendenteOptions() as $atendente)
                    <option value="{{ $atendente['id'] }}">{{ $atendente['nome'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-data-inicio">Data Início</label>
            <input id="os-data-inicio" type="date" wire:model="dataInicio" @disabled($readOnly) class="erp-os-form-input">
        </div>

        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-hora-inicio">Hora Início</label>
            <input id="os-hora-inicio" type="time" wire:model="horaInicio" @disabled($readOnly) class="erp-os-form-input">
        </div>

        <div class="erp-os-form-group erp-os-form-group--md">
            <label class="erp-os-form-label" for="os-previsao">Previsão Entrega</label>
            <input id="os-previsao" type="datetime-local" wire:model="previsaoEntrega" @disabled($readOnly) class="erp-os-form-input">
        </div>

        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-data-termino">Data Término</label>
            <input id="os-data-termino" type="date" wire:model="dataTermino" @disabled($readOnly) class="erp-os-form-input">
        </div>

        <div class="erp-os-form-group erp-os-form-group--sm">
            <label class="erp-os-form-label" for="os-hora-termino">Hora Término</label>
            <input id="os-hora-termino" type="time" wire:model="horaTermino" @disabled($readOnly) class="erp-os-form-input">
        </div>
    </div>
</div>

<div class="erp-os-panel erp-os-panel--fill">
    <div class="erp-os-item-tabs">
        <button
            type="button"
            wire:click="setActiveItemTab('servicos')"
            @class(['erp-os-item-tab', 'erp-os-item-tab--active' => $this->activeItemTab === 'servicos'])
        >Serviços</button>
        <button
            type="button"
            wire:click="setActiveItemTab('pecas')"
            @class(['erp-os-item-tab', 'erp-os-item-tab--active' => $this->activeItemTab === 'pecas'])
        >Peças</button>
    </div>

    @include('filament.components.erp.ordens-servico.form.itens', ['readOnly' => $readOnly])
</div>
