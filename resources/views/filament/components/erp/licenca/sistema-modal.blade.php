@php
    $statusTone = match ($this->status) {
        'ativo' => 'ok',
        'bloqueado', 'nao_encontrado', 'sem_cnpj' => 'warn',
        'indisponivel' => 'off',
        default => 'muted',
    };

    $waDigits = preg_replace('/\D/', '', $this->suporteWhatsapp) ?? '';
    $waLink = $waDigits !== '' ? 'https://wa.me/55'.$waDigits : null;
@endphp

<div
    class="erp-licenca-sistema-modal"
    wire:keydown.escape.window="closeScreen"
>
    <div class="erp-licenca-sistema-modal__backdrop" wire:click="closeScreen"></div>

    <div
        class="erp-licenca-sistema-modal__window"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-licenca-sistema-title"
        wire:click.stop
    >
        <div class="erp-licenca-sistema-modal__titlebar">
            <span id="erp-licenca-sistema-title">Chave de Liberação</span>
            <button
                type="button"
                class="erp-licenca-sistema-modal__close"
                wire:click="closeScreen"
                title="Fechar"
                aria-label="Fechar"
            >✕</button>
        </div>

        <div class="erp-licenca-sistema-modal__tabs" role="tablist">
            <button
                type="button"
                role="tab"
                class="erp-licenca-sistema-modal__tab {{ $this->aba === 'offline' ? 'is-active' : '' }}"
                wire:click="setAba('offline')"
                aria-selected="{{ $this->aba === 'offline' ? 'true' : 'false' }}"
            >Ativação Offline</button>
            <button
                type="button"
                role="tab"
                class="erp-licenca-sistema-modal__tab {{ $this->aba === 'online' ? 'is-active' : '' }}"
                wire:click="setAba('online')"
                aria-selected="{{ $this->aba === 'online' ? 'true' : 'false' }}"
            >Ativação Online</button>
        </div>

        <div class="erp-licenca-sistema-modal__body">
            @if ($this->aba === 'online')
                <div class="erp-licenca-sistema-modal__hero">
                    <div class="erp-licenca-sistema-modal__illus" aria-hidden="true">
                        <span class="erp-licenca-sistema-modal__server"></span>
                        <span class="erp-licenca-sistema-modal__server erp-licenca-sistema-modal__server--b"></span>
                    </div>
                    <p class="erp-licenca-sistema-modal__lead">
                        Essa opção se conecta ao nosso servidor para verificar se a licença
                        do sistema está cadastrada e válida. É necessário estar conectado à internet.
                    </p>
                </div>

                <div class="erp-licenca-sistema-modal__contact">
                    <p class="erp-licenca-sistema-modal__contact-title">Fale Conosco: Suporte</p>
                    <dl class="erp-licenca-sistema-modal__contact-list">
                        @if (filled($this->suporteSite))
                            <div>
                                <dt>Site</dt>
                                <dd>
                                    <a href="{{ $this->suporteSite }}" target="_blank" rel="noopener noreferrer">
                                        {{ $this->suporteSite }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt>Email</dt>
                            <dd>
                                <a href="mailto:{{ $this->suporteEmail }}">{{ $this->suporteEmail }}</a>
                            </dd>
                        </div>
                        <div>
                            <dt>WhatsApp</dt>
                            <dd>
                                @if ($waLink)
                                    <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer">
                                        {{ $this->suporteWhatsapp }}
                                    </a>
                                @else
                                    {{ $this->suporteWhatsapp }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="erp-licenca-sistema-modal__meta">
                    <div class="erp-licenca-sistema-modal__meta-row">
                        <span class="erp-licenca-sistema-modal__meta-label">CNPJ</span>
                        <span class="erp-licenca-sistema-modal__meta-value">{{ $this->cnpjMascarado }}</span>
                    </div>
                    @if (filled($this->nome))
                        <div class="erp-licenca-sistema-modal__meta-row">
                            <span class="erp-licenca-sistema-modal__meta-label">Cliente</span>
                            <span class="erp-licenca-sistema-modal__meta-value">{{ $this->nome }}</span>
                        </div>
                    @endif
                    <div class="erp-licenca-sistema-modal__meta-row">
                        <span class="erp-licenca-sistema-modal__meta-label">Situação no portal</span>
                        <span class="erp-licenca-sistema-modal__badge erp-licenca-sistema-modal__badge--{{ $statusTone }}">
                            {{ $this->statusLabel ?: '—' }}
                        </span>
                    </div>
                    <div class="erp-licenca-sistema-modal__meta-row">
                        <span class="erp-licenca-sistema-modal__meta-label">Vencimento da mensalidade</span>
                        <input
                            type="text"
                            class="erp-licenca-sistema-modal__validity"
                            value="{{ $this->validoAte !== '' ? $this->validoAte : '—' }}"
                            readonly
                            tabindex="-1"
                        >
                    </div>
                    <div class="erp-licenca-sistema-modal__meta-row">
                        <span class="erp-licenca-sistema-modal__meta-label">Computadores (PC)</span>
                        <span class="erp-licenca-sistema-modal__meta-value erp-licenca-sistema-modal__meta-value--usage">
                            {{ $this->formatLicencaUso($this->licencaPcEmUso, $this->licencaPcLimite) }}
                        </span>
                    </div>
                    <div class="erp-licenca-sistema-modal__meta-row">
                        <span class="erp-licenca-sistema-modal__meta-label">Telefones</span>
                        <span class="erp-licenca-sistema-modal__meta-value erp-licenca-sistema-modal__meta-value--usage">
                            {{ $this->formatLicencaUso($this->licencaTelEmUso, $this->licencaTelLimite) }}
                        </span>
                    </div>
                    @if (filled($this->mensagem))
                        <p class="erp-licenca-sistema-modal__hint">{{ $this->mensagem }}</p>
                    @endif
                </div>

                <div class="erp-licenca-sistema-modal__actions">
                    <button
                        type="button"
                        class="erp-licenca-sistema-modal__btn erp-licenca-sistema-modal__btn--primary"
                        wire:click="ativarOnline"
                        wire:loading.attr="disabled"
                        wire:target="ativarOnline"
                    >
                        <span class="erp-licenca-sistema-modal__lock" aria-hidden="true">🔐</span>
                        <span wire:loading.remove wire:target="ativarOnline">Ativar Online</span>
                        <span wire:loading wire:target="ativarOnline">Consultando portal…</span>
                    </button>

                    @if (filled($this->pagamentoUrl))
                        <a
                            href="{{ $this->pagamentoUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="erp-licenca-sistema-modal__btn erp-licenca-sistema-modal__btn--ghost"
                        >Abrir portal</a>
                    @endif
                </div>
            @else
                <div class="erp-licenca-sistema-modal__offline">
                    <p class="erp-licenca-sistema-modal__lead">
                        No ERP web a liberação é feita pela <strong>Ativação Online</strong>,
                        consultando o portal Unitec com o CNPJ da empresa.
                    </p>
                    <p class="erp-licenca-sistema-modal__hint">
                        Use a aba <strong>Ativação Online</strong> e clique em <strong>Ativar Online</strong>
                        para buscar e verificar se o CNPJ está cadastrado.
                    </p>
                    <button
                        type="button"
                        class="erp-licenca-sistema-modal__btn erp-licenca-sistema-modal__btn--primary"
                        wire:click="setAba('online')"
                    >Ir para Ativação Online</button>
                </div>
            @endif
        </div>

        <div class="erp-licenca-sistema-modal__footer">
            <span><strong>Computador:</strong> {{ $this->computador }}</span>
            <span><strong>MAC:</strong> {{ $this->mac }}</span>
        </div>
    </div>
</div>
