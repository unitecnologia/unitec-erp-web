@if ($this->activeModal === 'acesso_rapido')
    @php
        $editando = (bool) ($this->acessoRapidoEditando ?? false);
        $slots = (int) ($this->acessoRapidoSlotsCount ?? 30);
        $tiles = is_array($this->acessoRapidoTiles ?? null) ? $this->acessoRapidoTiles : [];
        $slotAlvo = $this->acessoRapidoSlotAlvo;
    @endphp

    <x-pdvui::modal-shell
        title="Acesso Rápido"
        title-id="erp-pdv-acesso-rapido-title"
        eyebrow="PDV"
        :subtitle="$editando ? 'Edite os atalhos e salve ao concluir' : 'Toque no produto para lançar no cupom'"
        close-action="fecharAcessoRapido"
        window-class="erp-pdv-modal__window--wide erp-pdv-acesso-rapido__window"
        class="erp-pdv-acesso-rapido"
    >
        <x-slot:icon>
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <rect x="3" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="2"/>
                <rect x="13" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="2"/>
                <rect x="3" y="13" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="2"/>
                <rect x="13" y="13" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="2"/>
            </svg>
        </x-slot:icon>

        <div class="erp-pdv-acesso-rapido__toolbar">
            <div class="erp-pdv-acesso-rapido__toolbar-left">
                @if ($editando)
                    <span class="erp-pdv-acesso-rapido__slots-label">Quadrados</span>
                    <button type="button" class="erp-pdv-acesso-rapido__qty-btn" wire:click="diminuirAcessoRapidoSlots" title="Diminuir">−</button>
                    <strong class="erp-pdv-acesso-rapido__slots-count">{{ $slots }}</strong>
                    <button type="button" class="erp-pdv-acesso-rapido__qty-btn" wire:click="aumentarAcessoRapidoSlots" title="Aumentar">+</button>
                @else
                    <span class="erp-pdv-acesso-rapido__hint">{{ $slots }} atalhos neste PDV</span>
                @endif
            </div>
            <button
                type="button"
                class="erp-pdv-acesso-rapido__edit-btn {{ $editando ? 'is-active' : '' }}"
                wire:click="toggleAcessoRapidoEditar"
            >
                {{ $editando ? 'Concluir edição' : 'Editar atalhos' }}
            </button>
        </div>

        @if ($editando)
            <div class="erp-pdv-acesso-rapido__search">
                <input
                    type="text"
                    wire:model.live.debounce.250ms="acessoRapidoBusca"
                    class="erp-pdv-acesso-rapido__search-input"
                    placeholder="Buscar produto por código ou nome para adicionar…"
                    autocomplete="off"
                    data-erp-uppercase
                >
                @if ($slotAlvo !== null)
                    <span class="erp-pdv-acesso-rapido__alvo">Slot {{ $slotAlvo + 1 }} selecionado</span>
                @endif
            </div>

            @if (($this->acessoRapidoBuscaResults ?? []) !== [])
                <div class="erp-pdv-acesso-rapido__results">
                    @foreach ($this->acessoRapidoBuscaResults as $row)
                        <button
                            type="button"
                            class="erp-pdv-acesso-rapido__result"
                            wire:click="atribuirProdutoAcessoRapido({{ (int) $row['product_id'] }})"
                        >
                            <span class="erp-pdv-acesso-rapido__result-code">{{ $row['codigo'] }}</span>
                            <span class="erp-pdv-acesso-rapido__result-name">{{ $row['descricao'] }}</span>
                            <span class="erp-pdv-acesso-rapido__result-price">R$ {{ $row['preco'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="erp-pdv-acesso-rapido__grid" style="--erp-ar-cols: {{ min(6, max(3, (int) ceil(sqrt($slots)))) }};">
            @for ($i = 0; $i < $slots; $i++)
                @php($tile = $tiles[$i] ?? null)
                <div
                    @class([
                        'erp-pdv-acesso-rapido__tile',
                        'is-empty' => $tile === null,
                        'is-selected' => $editando && $slotAlvo === $i,
                        'is-editing' => $editando,
                    ])
                >
                    @if ($tile === null)
                        @if ($editando)
                            <button
                                type="button"
                                class="erp-pdv-acesso-rapido__tile-btn"
                                wire:click="selecionarSlotAcessoRapido({{ $i }})"
                            >
                                <span class="erp-pdv-acesso-rapido__plus">+</span>
                                <span class="erp-pdv-acesso-rapido__empty-label">Vazio</span>
                            </button>
                        @else
                            <div class="erp-pdv-acesso-rapido__tile-btn" aria-hidden="true">
                                <span class="erp-pdv-acesso-rapido__empty-label">—</span>
                            </div>
                        @endif
                    @else
                        <button
                            type="button"
                            class="erp-pdv-acesso-rapido__tile-btn"
                            wire:click="lancarAcessoRapido({{ $i }})"
                        >
                            <span class="erp-pdv-acesso-rapido__name">{{ \Illuminate\Support\Str::limit($tile['descricao'], 42) }}</span>
                            <span class="erp-pdv-acesso-rapido__code">Cód. {{ $tile['codigo'] }}</span>
                            <span class="erp-pdv-acesso-rapido__price">R$ {{ $tile['preco'] }}</span>
                        </button>
                        @if ($editando)
                            <div class="erp-pdv-acesso-rapido__tile-actions">
                                <button type="button" wire:click="moverAcessoRapidoSlot({{ $i }}, -1)" title="Mover à esquerda" @disabled($i <= 0)>‹</button>
                                <button type="button" wire:click="moverAcessoRapidoSlot({{ $i }}, 1)" title="Mover à direita" @disabled($i >= $slots - 1)>›</button>
                                <button type="button" wire:click="removerAcessoRapidoSlot({{ $i }})" title="Remover" class="is-danger">×</button>
                            </div>
                        @endif
                    @endif
                </div>
            @endfor
        </div>

        <x-slot:footer>
            <button
                type="button"
                wire:click="fecharAcessoRapido"
                class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary erp-pdv-acesso-rapido__close-btn"
                title="Fechar (Esc)"
            >
                <kbd>Esc</kbd>
                <span>Fechar</span>
            </button>
        </x-slot:footer>
    </x-pdvui::modal-shell>
@endif
