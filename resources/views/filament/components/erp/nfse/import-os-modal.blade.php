@if ($this->nfseImportOsOpen)
    <div
        class="erp-lookup-modal erp-nfe-import-list-modal erp-nfse-import-os-modal"
        wire:keydown.escape="closeNfseImportOs"
        wire:keydown.f5.prevent="confirmarNfseImportOs"
        wire:keydown.arrow-down.prevent="moveNfseImportOsSelection(1)"
        wire:keydown.arrow-up.prevent="moveNfseImportOsSelection(-1)"
        wire:keydown.space.prevent="toggleNfseImportOsMarkFocused"
        wire:keydown.enter.prevent="confirmarNfseImportOs"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfseImportOs"></div>

        <div class="erp-lookup-modal__window erp-nfe-import-list-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfse-import-os-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfse-import-os-title">Importar — Ordem de Serviço</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfseImportOs" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-import-list-modal__body">
                <div class="erp-nfse-import-os-modal__layout">
                    <div class="erp-nfse-import-os-modal__filters">
                        <label class="erp-nfse-import-os-modal__field" for="erp-nfse-import-os-numero">
                            <span class="erp-nfse-import-os-modal__label">Número</span>
                            <input
                                id="erp-nfse-import-os-numero"
                                type="text"
                                wire:model.live.debounce.250ms="nfseImportOsNumero"
                                class="erp-nfse-import-os-modal__input"
                                data-erp-uppercase
                                autocomplete="off"
                            >
                        </label>

                        <label class="erp-nfse-import-os-modal__field erp-nfse-import-os-modal__field--grow" for="erp-nfse-import-os-cliente">
                            <span class="erp-nfse-import-os-modal__label">Cliente</span>
                            <input
                                id="erp-nfse-import-os-cliente"
                                type="text"
                                wire:model.live.debounce.250ms="nfseImportOsCliente"
                                class="erp-nfse-import-os-modal__input"
                                data-erp-uppercase
                                autocomplete="off"
                            >
                        </label>

                        <label class="erp-nfse-import-os-modal__field" for="erp-nfse-import-os-doc">
                            <span class="erp-nfse-import-os-modal__label">CPF/CNPJ</span>
                            <input
                                id="erp-nfse-import-os-doc"
                                type="text"
                                wire:model.live.debounce.250ms="nfseImportOsDocumento"
                                class="erp-nfse-import-os-modal__input"
                                autocomplete="off"
                            >
                        </label>

                        <label class="erp-nfse-import-os-modal__field" for="erp-nfse-import-os-sit">
                            <span class="erp-nfse-import-os-modal__label">Situação</span>
                            <select
                                id="erp-nfse-import-os-sit"
                                wire:model.live="nfseImportOsSituacao"
                                class="erp-nfse-import-os-modal__input"
                            >
                                @foreach ($this->nfseImportOsSituacaoOpcoes() as $opcao)
                                    <option value="{{ $opcao['value'] }}">{{ $opcao['label'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="erp-nfse-import-os-modal__field erp-nfse-import-os-modal__field--periodo">
                            <span class="erp-nfse-import-os-modal__label">Período</span>
                            <div class="erp-nfse-import-os-modal__periodo">
                                <input
                                    id="erp-nfse-import-os-data-de"
                                    type="date"
                                    wire:model.live="nfseImportOsDataDe"
                                    class="erp-nfse-import-os-modal__input erp-nfse-import-os-modal__input--date"
                                    title="Data inicial"
                                >
                                <span class="erp-nfse-import-os-modal__periodo-sep">até</span>
                                <input
                                    id="erp-nfse-import-os-data-ate"
                                    type="date"
                                    wire:model.live="nfseImportOsDataAte"
                                    class="erp-nfse-import-os-modal__input erp-nfse-import-os-modal__input--date"
                                    title="Data final"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="erp-nfse-import-os-modal__list-head" aria-hidden="true">
                        <span class="erp-nfse-import-os-modal__col-check"></span>
                        <span class="erp-nfse-import-os-modal__col-numero">OS</span>
                        <span class="erp-nfse-import-os-modal__col-data">Data</span>
                        <span class="erp-nfse-import-os-modal__col-cliente">Cliente</span>
                        <span class="erp-nfse-import-os-modal__col-doc">CPF/CNPJ</span>
                        <span class="erp-nfse-import-os-modal__col-sit">Situação</span>
                        <span class="erp-nfse-import-os-modal__col-total">Serviços</span>
                    </div>

                    <div class="erp-nfse-import-os-modal__list" role="listbox" aria-label="Ordens de serviço">
                        @forelse ($this->nfseImportOsResults as $index => $row)
                            @php
                                $isMarked = $this->isNfseImportOsRowMarked((int) $index);
                                $isFocused = $this->nfseImportOsSelectedIndex !== null
                                    && (int) $this->nfseImportOsSelectedIndex === (int) $index;
                                $bloqueada = empty($row['importavel']);
                                $sit = (string) ($row['situacao_codigo'] ?? '');
                            @endphp
                            <button
                                type="button"
                                role="option"
                                aria-selected="{{ $isMarked ? 'true' : 'false' }}"
                                wire:click="selectNfseImportOsRow({{ $index }})"
                                wire:dblclick="confirmarNfseImportOs"
                                wire:key="nfse-import-os-{{ $row['id'] ?? $index }}"
                                @class([
                                    'erp-nfse-import-os-modal__item',
                                    'erp-nfse-import-os-modal__item--focused' => $isFocused,
                                    'erp-nfse-import-os-modal__item--marked' => $isMarked,
                                    'erp-nfse-import-os-modal__item--bloqueada' => $bloqueada,
                                ])
                                title="{{ $bloqueada ? ($row['bloqueio'] ?? 'OS não importável') : 'Clique para selecionar · F5 importa' }}"
                            >
                                <span
                                    class="erp-nfse-import-os-modal__check"
                                    wire:click.stop="toggleNfseImportOsMarkAt({{ $index }})"
                                    aria-hidden="true"
                                ></span>
                                <span class="erp-nfse-import-os-modal__col-numero">{{ $row['numero'] ?? '—' }}</span>
                                <span class="erp-nfse-import-os-modal__col-data">{{ $row['data'] ?? '—' }}</span>
                                <span class="erp-nfse-import-os-modal__col-cliente" title="{{ $row['cliente'] ?? '—' }}">{{ $row['cliente'] ?? '—' }}</span>
                                <span class="erp-nfse-import-os-modal__col-doc" title="{{ $row['documento'] ?: '—' }}">{{ $row['documento'] ?: '—' }}</span>
                                <span class="erp-nfse-import-os-modal__col-sit">
                                    <span @class([
                                        'erp-nfse-import-os-modal__badge',
                                        'erp-nfse-import-os-modal__badge--finalizada' => $sit === 'finalizada',
                                        'erp-nfse-import-os-modal__badge--entregue' => $sit === 'entregue',
                                    ])>{{ $row['situacao'] ?? '—' }}</span>
                                </span>
                                <span class="erp-nfse-import-os-modal__col-total">R$ {{ $row['total'] ?? '0,00' }}</span>
                            </button>
                        @empty
                            <div class="erp-nfse-import-os-modal__empty">
                                Nenhuma OS finalizada no período.
                                <span>O filtro inicia no dia de hoje. Amplie o período ou altere a situação.</span>
                            </div>
                        @endforelse
                    </div>

                    @if ($this->nfseImportOsMarkedId)
                        <p class="erp-nfse-import-os-modal__hint">
                            OS marcada para importar. Somente serviços entram na NFS-e.
                        </p>
                    @elseif ($this->nfseImportOsSelectedIndex !== null && ! empty($this->nfseImportOsResults[$this->nfseImportOsSelectedIndex]['bloqueio']))
                        <p class="erp-nfse-import-os-modal__hint erp-nfse-import-os-modal__hint--warn">
                            {{ $this->nfseImportOsResults[$this->nfseImportOsSelectedIndex]['bloqueio'] }}
                        </p>
                    @else
                        <p class="erp-nfse-import-os-modal__hint">
                            Clique na linha para selecionar a OS · Espaço marca/desmarca · F5 importa
                        </p>
                    @endif
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-import-list-modal__actions erp-nfse-import-os-modal__actions">
                <button type="button" wire:click="confirmarNfseImportOs" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon">↓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Importar</span>
                </button>
                <button type="button" wire:click="closeNfseImportOs" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
@endif

@if ($this->nfseImportOsConfirmOpen)
    <div class="erp-lookup-modal erp-nfse-producao-modal" style="z-index: 120;">
        <div class="erp-lookup-modal__backdrop" wire:click="cancelarNfseImportOsDuplicada"></div>
        <div class="erp-nfe-fiscal-overlay" role="dialog" aria-modal="true" aria-labelledby="erp-nfse-import-os-dup-title">
            <div class="erp-nfe-fiscal-overlay__card">
                <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>
                <h2 id="erp-nfse-import-os-dup-title" class="erp-nfe-fiscal-overlay__title">OS já importada</h2>
                <div class="erp-nfe-fiscal-overlay__text">
                    Esta Ordem de Serviço já possui serviços nesta NFS-e.
                    Deseja substituir os serviços importados dessa OS?
                </div>
                <div class="erp-nfe-fiscal-overlay__actions">
                    <button
                        type="button"
                        class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--confirm"
                        wire:click="confirmarNfseImportOsDuplicada"
                    >Sim</button>
                    <button
                        type="button"
                        class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
                        wire:click="cancelarNfseImportOsDuplicada"
                    >Não</button>
                </div>
                <p class="erp-nfe-fiscal-overlay__hint">Confirme para evitar duplicidade silenciosa.</p>
            </div>
        </div>
    </div>
@endif
