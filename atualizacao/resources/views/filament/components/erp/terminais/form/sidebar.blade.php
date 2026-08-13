<aside class="erp-terminais-master__sidebar">
    @php
        $machineName = \App\Support\Erp\Pdv\TerminalResolver::make()->resolveMachineName();
    @endphp

    @if ($this->isNewTerminal)
        <div class="erp-terminais-master__sidebar-new">
            <label class="erp-terminais-master__sidebar-new-label" for="term-novo-nome">Nome do novo terminal (PDV)</label>
            <input
                id="term-novo-nome"
                type="text"
                wire:model="data.nome"
                class="erp-terminais-master__nome-input"
                placeholder="Ex.: pdv1"
                autocomplete="off"
            >
        </div>
    @endif

    <div class="erp-terminais-master__grid-head" role="row">
        <span class="erp-terminais-master__col erp-terminais-master__col--nome">Nome PDV</span>
        <span class="erp-terminais-master__col erp-terminais-master__col--ativo" title="Marcado = liberado / Desmarcado = bloqueado">Ativo</span>
        <span class="erp-terminais-master__col erp-terminais-master__col--info">Info</span>
    </div>

    <ul class="erp-terminais-master__list" role="listbox" aria-label="Terminais">
        @forelse ($this->terminals as $terminal)
            @php
                $selected = ! $this->isNewTerminal && (int) ($this->editingTerminalId ?? $this->highlightedRecordId) === (int) $terminal->id;
                $ativo = (bool) ($terminal->ativo ?? true);
                $infoOpen = (int) ($this->terminalInfoId ?? 0) === (int) $terminal->id;
            @endphp
            <li @class(['erp-terminais-master__row', 'erp-terminais-master__row--info-open' => $infoOpen])>
                <div
                    @class([
                        'erp-terminais-master__item',
                        'erp-terminais-master__item--selected' => $selected,
                        'erp-terminais-master__item--machine' => strtoupper((string) $terminal->nome) === $machineName,
                        'erp-terminais-master__item--inactive' => ! $ativo,
                    ])
                    role="option"
                    aria-selected="{{ $selected ? 'true' : 'false' }}"
                >
                    <button
                        type="button"
                        class="erp-terminais-master__select"
                        wire:click="selectTerminal({{ $terminal->id }})"
                        title="{{ $terminal->nome }}"
                    >
                        <span class="erp-terminais-master__col erp-terminais-master__col--nome">
                            {{ $terminal->nome }}
                            @if (filled($terminal->numero_logico_terminal))
                                <small class="erp-terminais-master__item-num">Nº {{ $terminal->numero_logico_terminal }}</small>
                            @endif
                        </span>
                    </button>

                    <label
                        class="erp-terminais-master__col erp-terminais-master__col--ativo"
                        title="{{ $ativo ? 'Ativo (liberado) — clique para bloquear' : 'Bloqueado — clique para liberar' }}"
                        wire:click.stop
                    >
                        <input
                            type="checkbox"
                            class="erp-terminais-master__ativo-flag"
                            @checked($ativo)
                            wire:click.prevent.stop="toggleTerminalAtivo({{ $terminal->id }})"
                        >
                    </label>

                    <button
                        type="button"
                        class="erp-terminais-master__col erp-terminais-master__col--info erp-terminais-master__info-btn"
                        wire:click.stop="toggleTerminalInfo({{ $terminal->id }})"
                        title="Ver IP, ID e empresa"
                        aria-label="Ver informações do terminal"
                        aria-expanded="{{ $infoOpen ? 'true' : 'false' }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>

                @if ($infoOpen)
                    <div class="erp-terminais-master__info-panel" role="region" aria-label="Informações do terminal">
                        <div class="erp-terminais-master__info-grid">
                            <div>
                                <span class="erp-terminais-master__info-label">IP</span>
                                <strong>{{ $terminal->ip ?: '—' }}</strong>
                            </div>
                            <div>
                                <span class="erp-terminais-master__info-label">ID</span>
                                <strong>{{ $terminal->id }}</strong>
                            </div>
                            <div>
                                <span class="erp-terminais-master__info-label">Empresa</span>
                                <strong>{{ $terminal->empresa_id }}</strong>
                            </div>
                            <div>
                                <span class="erp-terminais-master__info-label">Nº lógico</span>
                                <strong>{{ $terminal->numero_logico_terminal ?: '—' }}</strong>
                            </div>
                            <div class="erp-terminais-master__info-status">
                                <span class="erp-terminais-master__info-label">Status</span>
                                <strong>{{ $ativo ? 'Liberado' : 'Bloqueado' }}</strong>
                            </div>
                        </div>
                        <button type="button" class="erp-terminais-master__info-close" wire:click="closeTerminalInfo" title="Fechar">✕</button>
                    </div>
                @endif
            </li>
        @empty
            <li class="erp-terminais-master__empty">Nenhum terminal cadastrado</li>
        @endforelse
    </ul>
</aside>
