@php
    $empresas = $this->empresasDisponiveis();
    $qtd = count($empresas);
    $selecionada = collect($empresas)->firstWhere('id', (int) $this->selectedEmpresaId);
@endphp

<div
    class="erp-trocar-empresa"
    wire:keydown.escape.window="closeScreen"
>
    <div class="erp-trocar-empresa__backdrop" wire:click="closeScreen" aria-hidden="true"></div>

    <div
        class="erp-trocar-empresa__panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-trocar-empresa-title"
    >
        <header class="erp-trocar-empresa__header">
            <div class="erp-trocar-empresa__brand">
                <span class="erp-trocar-empresa__mark" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18"/>
                        <path d="M5 21V8l7-4 7 4v13"/>
                        <path d="M9 21v-5h6v5"/>
                        <path d="M9 10h.01"/>
                        <path d="M15 10h.01"/>
                        <path d="M12 10h.01"/>
                    </svg>
                </span>
                <div class="erp-trocar-empresa__titles">
                    <h1 id="erp-trocar-empresa-title" class="erp-trocar-empresa__title">Trocar empresa</h1>
                    <p class="erp-trocar-empresa__subtitle">Mude a empresa ativa sem sair do sistema</p>
                </div>
            </div>
            <button
                type="button"
                class="erp-trocar-empresa__close"
                wire:click="closeScreen"
                title="Fechar (Esc)"
                aria-label="Fechar"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                    <path d="M18 6L6 18"/>
                    <path d="M6 6l12 12"/>
                </svg>
            </button>
        </header>

        <div class="erp-trocar-empresa__body">
            @if ($qtd === 0)
                <div class="erp-trocar-empresa__empty-box">
                    <span class="erp-trocar-empresa__empty-icon" aria-hidden="true">!</span>
                    <p class="erp-trocar-empresa__empty">Nenhuma empresa liberada para o seu usuário.</p>
                </div>
            @elseif ($qtd === 1)
                <p class="erp-trocar-empresa__hint">Você tem acesso a apenas uma empresa.</p>
                @foreach ($empresas as $empresa)
                    @php
                        $initials = collect(preg_split('/\s+/', trim($empresa['label'])) ?: [])
                            ->filter()
                            ->take(2)
                            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                            ->implode('');
                    @endphp
                    <div class="erp-trocar-empresa__item is-selected erp-trocar-empresa__item--atual" role="listitem">
                        <span class="erp-trocar-empresa__avatar" aria-hidden="true">{{ $initials !== '' ? $initials : 'E' }}</span>
                        <div class="erp-trocar-empresa__item-main">
                            <span class="erp-trocar-empresa__item-name">{{ $empresa['label'] }}</span>
                            <span class="erp-trocar-empresa__item-cnpj">CNPJ {{ $empresa['cnpj'] }}</span>
                        </div>
                        <span class="erp-trocar-empresa__badge">Atual</span>
                    </div>
                    <div class="erp-trocar-empresa__actions">
                        <button type="button" class="erp-trocar-empresa__btn erp-trocar-empresa__btn--primary" wire:click="closeScreen">
                            Fechar
                        </button>
                    </div>
                @endforeach
            @else
                <div class="erp-trocar-empresa__meta">
                    <p class="erp-trocar-empresa__hint">Selecione a empresa e confirme.</p>
                    <span class="erp-trocar-empresa__count">{{ $qtd }} empresas</span>
                </div>

                <ul class="erp-trocar-empresa__list" role="listbox" aria-label="Empresas">
                    @foreach ($empresas as $empresa)
                        @php
                            $selected = (int) $this->selectedEmpresaId === (int) $empresa['id'];
                            $initials = collect(preg_split('/\s+/', trim($empresa['label'])) ?: [])
                                ->filter()
                                ->take(2)
                                ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                                ->implode('');
                        @endphp
                        <li>
                            <button
                                type="button"
                                class="erp-trocar-empresa__item{{ $selected ? ' is-selected' : '' }}{{ $empresa['atual'] ? ' erp-trocar-empresa__item--atual' : '' }}"
                                wire:click="selecionarEmpresa({{ $empresa['id'] }})"
                                wire:dblclick="selecionarEConfirmar({{ $empresa['id'] }})"
                                role="option"
                                aria-selected="{{ $selected ? 'true' : 'false' }}"
                            >
                                <span class="erp-trocar-empresa__radio" aria-hidden="true"></span>
                                <span class="erp-trocar-empresa__avatar" aria-hidden="true">{{ $initials !== '' ? $initials : 'E' }}</span>
                                <span class="erp-trocar-empresa__item-main">
                                    <span class="erp-trocar-empresa__item-name">{{ $empresa['label'] }}</span>
                                    <span class="erp-trocar-empresa__item-cnpj">CNPJ {{ $empresa['cnpj'] }}</span>
                                </span>
                                @if ($empresa['atual'])
                                    <span class="erp-trocar-empresa__badge">Atual</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                @if ($selecionada)
                    <p class="erp-trocar-empresa__selected-hint">
                        Selecionada: <strong>{{ $selecionada['label'] }}</strong>
                    </p>
                @endif

                <div class="erp-trocar-empresa__actions">
                    <button type="button" class="erp-trocar-empresa__btn erp-trocar-empresa__btn--ghost" wire:click="closeScreen">
                        Cancelar
                    </button>
                    <button type="button" class="erp-trocar-empresa__btn erp-trocar-empresa__btn--primary" wire:click="confirmarTrocaEmpresa">
                        Confirmar
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
