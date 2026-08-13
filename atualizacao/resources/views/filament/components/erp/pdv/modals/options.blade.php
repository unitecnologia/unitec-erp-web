@if ($this->activeModal === 'options')
    @php($caixaAberto = (bool) ($this->caixaAberto ?? false))
    <div class="erp-pdv-modal erp-pdv-modal--menu" role="dialog" aria-label="Opções do PDV">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>
        <div class="erp-pdv-options" id="erp-pdv-options-panel">
            <header class="erp-pdv-options__header">
                <span class="erp-pdv-options__header-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>
                    </svg>
                </span>
                <div class="erp-pdv-options__header-text">
                    <strong>Opções do PDV</strong>
                    <small>{{ $caixaAberto ? 'Caixa aberto' : 'Caixa fechado' }}</small>
                </div>
                <button type="button" class="erp-pdv-options__close" wire:click="closePdvModal" title="Fechar" aria-label="Fechar">×</button>
            </header>

            <ul class="erp-pdv-options__list" role="menu">
                <li>
                    <button type="button" role="menuitem" wire:click="toggleCaixa">
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="12" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">{{ $caixaAberto ? 'Fechar Caixa' : 'Abrir Caixa' }}</span>
                        <kbd>F2</kbd>
                    </button>
                </li>

                @if ($this->pdvExibirF3Vendedor)
                    <li>
                        <button type="button" role="menuitem" wire:click="openVendedorModal" @disabled(! $caixaAberto)>
                            <span class="erp-pdv-options__ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.6 3.1-5.6 7-5.6s7 2 7 5.6"/></svg>
                            </span>
                            <span class="erp-pdv-options__txt">Vendedor</span>
                            <kbd>F3</kbd>
                        </button>
                    </li>
                @endif

                <li>
                    <button type="button" role="menuitem" wire:click="openBuscaAvancadaModal" @disabled(! $caixaAberto)>
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Busca Avançada</span>
                        <kbd>F4</kbd>
                    </button>
                </li>

                <li>
                    <button type="button" role="menuitem" wire:click="openRemoverItensModal" @disabled(! $caixaAberto)>
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Remover Itens</span>
                        <kbd>F11</kbd>
                    </button>
                </li>

                <li>
                    <button type="button" role="menuitem" wire:click="deletarItemCupom" @disabled(! $caixaAberto)>
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Deleta Item</span>
                        <kbd>Del</kbd>
                    </button>
                </li>

                @if ($this->pdvPermitirDescontoItem)
                    <li>
                        <button type="button" role="menuitem" wire:click="openDescontoItemModal" @disabled(! $caixaAberto)>
                            <span class="erp-pdv-options__ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M19 5 5 19"/><circle cx="7.5" cy="7.5" r="2"/><circle cx="16.5" cy="16.5" r="2"/></svg>
                            </span>
                            <span class="erp-pdv-options__txt">Desconto / Acréscimo</span>
                            <kbd>Ctrl+D</kbd>
                        </button>
                    </li>
                @endif

                @if ($this->pdvHabilitarTabelaPreco)
                    <li>
                        <button type="button" role="menuitem" wire:click="openTabelaPrecoModal" @disabled(! $caixaAberto)>
                            <span class="erp-pdv-options__ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h10"/></svg>
                            </span>
                            <span class="erp-pdv-options__txt">Tabela de Preço</span>
                            <kbd></kbd>
                        </button>
                    </li>
                @endif

                <li>
                    <button type="button" role="menuitem" wire:click="abrirGaveta" @disabled(! $caixaAberto)>
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 12h18"/><path d="M9 16h6"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Abrir Gaveta</span>
                        <kbd>Ctrl+A</kbd>
                    </button>
                </li>

                <li>
                    <button type="button" role="menuitem" wire:click="openReceberModal" @disabled(! $caixaAberto)>
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.3"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Receber</span>
                        <kbd>Ctrl+R</kbd>
                    </button>
                </li>

                <li>
                    <button type="button" role="menuitem" wire:click="openBuscaPrecoModal" @disabled(! $caixaAberto)>
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 13 21a2 2 0 0 1-2.8 0l-6.2-6.2a2 2 0 0 1-.6-1.4V5a2 2 0 0 1 2-2h5.4a2 2 0 0 1 1.4.6l6.4 6.4a2 2 0 0 1 0 2.8Z"/><circle cx="8.5" cy="8.5" r="1"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Busca Preço</span>
                        <kbd>Ctrl+L</kbd>
                    </button>
                </li>

                @if ($this->pdvUsaTef)
                    <li>
                        <button type="button" role="menuitem" wire:click="moduleStubTef" @disabled(! $caixaAberto)>
                            <span class="erp-pdv-options__ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="2"/><path d="M9 7h6"/><path d="M9 18h6"/></svg>
                            </span>
                            <span class="erp-pdv-options__txt">Administrativo TEF</span>
                            <kbd>Ctrl+T</kbd>
                        </button>
                    </li>
                @endif

                <li>
                    <button type="button" role="menuitem" wire:click="moduleStubNfce" @disabled(! $caixaAberto)>
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V4h12v5"/><rect x="4" y="9" width="16" height="7" rx="1.5"/><path d="M7 16v4h10v-4"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Reimprimir NFCe</span>
                        <kbd>Ctrl+I</kbd>
                    </button>
                </li>

                <li>
                    <button type="button" role="menuitem" wire:click="openConsultaVendaModal" @disabled(! $caixaAberto)>
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M8.5 12.5 11 15l4.5-4.5"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Consulta / Estorno Venda</span>
                        <kbd>Ctrl+O</kbd>
                    </button>
                </li>

                <li>
                    <button type="button" role="menuitem" wire:click="openReimprimirModal" @disabled(! $caixaAberto)>
                        <span class="erp-pdv-options__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V4h12v5"/><rect x="4" y="9" width="16" height="7" rx="1.5"/><path d="M7 16v4h10v-4"/></svg>
                        </span>
                        <span class="erp-pdv-options__txt">Reimprimir Pedido</span>
                        <kbd>Ctrl+P</kbd>
                    </button>
                </li>
            </ul>

            @if ($this->pdvExibeMesas)
                <p class="erp-pdv-options__section-title">Módulo Mesas</p>
                <ul class="erp-pdv-options__list erp-pdv-options__list--secondary" role="menu">
                    <li>
                        <button type="button" role="menuitem" wire:click="moduleStubMesa('Imprimir Pedido')" @disabled(! $caixaAberto)>
                            <span class="erp-pdv-options__ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V4h12v5"/><rect x="4" y="9" width="16" height="7" rx="1.5"/><path d="M7 16v4h10v-4"/></svg>
                            </span>
                            <span class="erp-pdv-options__txt">Imprimir Pedido</span>
                            <kbd>Ctrl+S</kbd>
                        </button>
                    </li>
                    <li>
                        <button type="button" role="menuitem" wire:click="moduleStubMesa('Abrir Mesa')" @disabled(! $caixaAberto)>
                            <span class="erp-pdv-options__ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h16"/><path d="M6 10V8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M6 10v8"/><path d="M18 10v8"/></svg>
                            </span>
                            <span class="erp-pdv-options__txt">Abrir Mesa</span>
                            <kbd>Ctrl+N</kbd>
                        </button>
                    </li>
                    <li>
                        <button type="button" role="menuitem" wire:click="moduleStubMesa('Imprimir Item')" @disabled(! $caixaAberto)>
                            <span class="erp-pdv-options__ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V4h12v5"/><rect x="4" y="9" width="16" height="7" rx="1.5"/><path d="M7 16v4h10v-4"/></svg>
                            </span>
                            <span class="erp-pdv-options__txt">Imprimir Item</span>
                            <kbd>Ctrl+E</kbd>
                        </button>
                    </li>
                    <li>
                        <button type="button" role="menuitem" wire:click="moduleStubMesa('Transferir Mesa')" @disabled(! $caixaAberto)>
                            <span class="erp-pdv-options__ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h11l-3-3"/><path d="M17 17H6l3 3"/></svg>
                            </span>
                            <span class="erp-pdv-options__txt">Transferir Mesa</span>
                            <kbd>Ctrl+B</kbd>
                        </button>
                    </li>
                    <li>
                        <button type="button" role="menuitem" wire:click="moduleStubMesa('Atualiza Mesas')" @disabled(! $caixaAberto)>
                            <span class="erp-pdv-options__ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 0 0-14-4.5L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14 4.5L20 16"/><path d="M20 20v-4h-4"/></svg>
                            </span>
                            <span class="erp-pdv-options__txt">Atualiza Mesas</span>
                            <kbd>Ctrl+M</kbd>
                        </button>
                    </li>
                </ul>
            @endif
        </div>
    </div>
@endif
