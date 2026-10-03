<div class="erp-os-panel erp-os-panel--fill erp-os-equipamento">
    <h3 class="erp-os-panel__title">Equipamento</h3>

    <div class="erp-os-form-row erp-os-equipamento__linha erp-os-equipamento__linha--identificacao">
        <div class="erp-os-form-group erp-os-form-group--placa">
            <label class="erp-os-form-label" for="os-placa">Placa</label>
            <div class="erp-os-placa-field">
                <input
                    id="os-placa"
                    type="text"
                    wire:model="placa"
                    wire:blur="aplicarVeiculoLocalDaPlaca($event.target.value)"
                    @disabled($readOnly)
                    class="erp-os-form-input"
                >
                <button
                    type="button"
                    class="erp-os-placa-btn"
                    wire:click="consultarPlaca"
                    wire:loading.attr="disabled"
                    wire:target="consultarPlaca"
                    @disabled($readOnly)
                >
                    <span wire:loading.remove wire:target="consultarPlaca">Consultar placa</span>
                    <span wire:loading wire:target="consultarPlaca">Consultando…</span>
                </button>
            </div>
        </div>
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-descricao">Equipamento / Marca</label>
            <div class="erp-os-equipamento-field">
                <input
                    id="os-descricao"
                    type="text"
                    wire:model.live.debounce.250ms="descricao"
                    wire:focus="openEquipamentoLookup"
                    wire:blur="closeEquipamentoLookup"
                    wire:keydown.escape.prevent="closeEquipamentoLookup"
                    wire:keydown.enter.prevent="handleEquipamentoEnter"
                    @if ($this->equipamentoLookupOpen)
                        wire:keydown.arrow-up.prevent="moveEquipamentoSelection(-1)"
                        wire:keydown.arrow-down.prevent="moveEquipamentoSelection(1)"
                    @endif
                    @disabled($readOnly)
                    class="erp-os-form-input"
                    autocomplete="off"
                    placeholder="Digite para localizar"
                >
                @if ($this->equipamentoLookupOpen && filled(trim($this->descricao)))
                    @if ($this->equipamentoResults !== [])
                        <div class="erp-orc-cliente-lookup erp-os-equipamento-lookup">
                            <table class="erp-orc-cliente-lookup__table">
                                <thead>
                                    <tr>
                                        <th>Placa</th>
                                        <th>Equipamento</th>
                                        <th>Modelo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->equipamentoResults as $index => $row)
                                        <tr
                                            wire:key="os-equipamento-{{ $row['id'] }}"
                                            wire:mousedown.prevent="selectEquipamentoResult({{ $index }})"
                                            @class(['erp-orc-cliente-lookup__row', 'erp-orc-cliente-lookup__row--active' => $this->selectedEquipamentoIndex === $index])
                                        >
                                            <td>{{ $row['placa'] }}</td>
                                            <td>{{ $row['descricao'] ?: '—' }}</td>
                                            <td>{{ $row['modelo'] ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="erp-orc-cliente-lookup erp-orc-cliente-lookup--empty erp-os-equipamento-lookup">
                            Nenhum equipamento cadastrado.
                        </div>
                    @endif
                @endif
            </div>
        </div>
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-modelo">Modelo</label>
            <input id="os-modelo" type="text" wire:model="modelo" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-versao">Versão</label>
            <input id="os-versao" type="text" wire:model="veiculoVersao" class="erp-os-form-input erp-os-form-input--cadastro" disabled tabindex="-1" title="{{ $this->veiculoVersao }}">
        </div>
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-ano">Ano Fab./Modelo</label>
            <input id="os-ano" type="text" wire:model="ano" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-serie">Nº Série / IMEI</label>
            <input id="os-serie" type="text" wire:model="numeroSerie" @disabled($readOnly) class="erp-os-form-input">
        </div>
    </div>

    <div class="erp-os-form-row erp-os-equipamento__linha erp-os-equipamento__linha--placa">
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-placa-alt">Placa alt.</label>
            <input id="os-placa-alt" type="text" wire:model="veiculoPlacaAlternativa" class="erp-os-form-input erp-os-form-input--cadastro" disabled tabindex="-1">
        </div>
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-km">KM</label>
            <input id="os-km" type="text" wire:model="km" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-cor">Cor</label>
            <input id="os-cor" type="text" wire:model="corVeiculo" @disabled($readOnly) class="erp-os-form-input">
        </div>
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-combustivel">Combustível</label>
            <input id="os-combustivel" type="text" wire:model="veiculoCombustivel" class="erp-os-form-input erp-os-form-input--cadastro" disabled tabindex="-1" title="{{ $this->veiculoCombustivel }}">
        </div>
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-chassi">Chassi</label>
            <input id="os-chassi" type="text" wire:model="chassiVeiculo" @disabled($readOnly) class="erp-os-form-input">
        </div>
    </div>

    <div @class([
        'erp-os-form-row erp-os-equipamento__linha erp-os-equipamento__linha--complemento',
        'erp-os-equipamento__linha--renavam' => filled($this->veiculoRenavam),
    ])>
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-tipo-especie">Tipo/Espécie</label>
            <input id="os-tipo-especie" type="text" wire:model="veiculoTipoEspecie" class="erp-os-form-input erp-os-form-input--cadastro" disabled tabindex="-1" title="{{ $this->veiculoTipoEspecie }}">
        </div>
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-carroceria">Carroceria</label>
            <input id="os-carroceria" type="text" wire:model="veiculoCarroceria" class="erp-os-form-input erp-os-form-input--cadastro" disabled tabindex="-1" title="{{ $this->veiculoCarroceria }}">
        </div>
        <div class="erp-os-form-group">
            <label class="erp-os-form-label" for="os-cidade-uf">Cidade/UF</label>
            <input id="os-cidade-uf" type="text" wire:model="veiculoCidadeUf" class="erp-os-form-input erp-os-form-input--cadastro" disabled tabindex="-1" title="{{ $this->veiculoCidadeUf }}">
        </div>
        @if (filled($this->veiculoRenavam))
            <div class="erp-os-form-group">
                <label class="erp-os-form-label" for="os-renavam">RENAVAM</label>
                <input id="os-renavam" type="text" wire:model="veiculoRenavam" class="erp-os-form-input erp-os-form-input--cadastro" disabled tabindex="-1">
            </div>
        @endif
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-descricao2">Descrição / Complemento</label>
            <input id="os-descricao2" type="text" wire:model="descricao2" @disabled($readOnly) class="erp-os-form-input">
        </div>
    </div>
</div>
