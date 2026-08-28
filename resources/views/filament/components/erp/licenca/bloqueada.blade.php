@php
    $titulo = match ($this->status) {
        'sem_cnpj' => 'CNPJ não cadastrado',
        'nao_encontrado' => 'Cliente não encontrado',
        default => 'Sistema bloqueado',
    };

    $descricao = match ($this->status) {
        'sem_cnpj' => 'Informe o CNPJ da empresa no cadastro para validarmos a licença.',
        'nao_encontrado' => 'Este CNPJ não está no gerenciador de licenças da Unitec.',
        default => filled($this->mensagem)
            ? $this->mensagem
            : 'O acesso foi bloqueado no gerenciador de licenças. Entre em contato com o suporte Unitec para liberar.',
    };

    $vencimentoLabel = ($this->mensalidadeVencida ?? false) ? 'Vencimento' : 'Válido até';
@endphp

{{-- Overlay + shell: estilos críticos inline (não dependem de cache do CSS) --}}
<style>
    .fi-body:has(.erp-licenca-bloqueada-page) .erp-shell,
    .fi-body.erp-licenca-bloqueada-body .erp-shell { display: none !important; }
    .fi-body:has(.erp-licenca-bloqueada-page),
    .fi-body.erp-licenca-bloqueada-body {
        overflow: hidden !important;
        background: #0b1a2e !important;
    }
    .erp-licenca-bloqueada {
        position: fixed !important;
        inset: 0 !important;
        z-index: 2147483000 !important;
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 1rem;
        box-sizing: border-box;
        background:
            radial-gradient(900px 420px at 50% 12%, rgba(59, 130, 246, 0.22), transparent 60%),
            linear-gradient(165deg, #0b1a2e 0%, #13233f 42%, #1e3a5f 100%) !important;
    }
    .erp-licenca-bloqueada__brand {
        color: rgba(226, 232, 240, 0.9);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    .erp-licenca-bloqueada__card {
        width: min(23.5rem, calc(100vw - 1.5rem));
        max-height: calc(100dvh - 3.5rem);
        overflow: auto;
        padding: 1rem 1.05rem 0.95rem;
        border-radius: 16px;
        background: #fff !important;
        color: #0f172a;
        box-shadow: 0 24px 48px rgba(2, 6, 23, 0.45);
        box-sizing: border-box;
    }
</style>

<div
    class="erp-licenca-bloqueada"
    x-data="{
        copied: false,
        async copyPix(code) {
            if (!code) return;
            try {
                await navigator.clipboard.writeText(code);
                this.copied = true;
                setTimeout(() => this.copied = false, 1800);
            } catch (e) {
                const input = this.$refs.pixInput;
                if (input) { input.focus(); input.select(); }
            }
        }
    }"
>
    <div class="erp-licenca-bloqueada__brand">{{ config('unitec.app_name') }}</div>

    <div class="erp-licenca-bloqueada__card">
        <div class="erp-licenca-bloqueada__badge">Licença</div>
        <h1 class="erp-licenca-bloqueada__title">{{ $titulo }}</h1>
        <p class="erp-licenca-bloqueada__desc">{{ $descricao }}</p>

        <dl class="erp-licenca-bloqueada__meta">
            @if (filled($this->nome))
                <div>
                    <dt>Cliente</dt>
                    <dd>{{ $this->nome }}</dd>
                </div>
            @endif
            @if (filled($this->cnpj))
                <div>
                    <dt>CNPJ</dt>
                    <dd>{{ $this->cnpj }}</dd>
                </div>
            @endif
            @if (filled($this->validoAte))
                <div>
                    <dt>{{ $vencimentoLabel }}</dt>
                    <dd>{{ \Illuminate\Support\Carbon::parse($this->validoAte)->format('d/m/Y') }}</dd>
                </div>
            @endif
        </dl>

        @if ($this->mensalidadeVencida ?? false)
            @if ($this->pixLoading ?? false)
                <p class="erp-licenca-bloqueada__pix-loading">Carregando Pix…</p>
            @elseif (filled($this->pixQrDataUrl ?? null) || filled($this->pixBrCode ?? null))
                <div class="erp-licenca-bloqueada__pix">
                    <div class="erp-licenca-bloqueada__pix-head">
                        <strong>Pagar com Pix</strong>
                        @if (filled($this->pixAmount ?? null))
                            <span>{{ $this->pixAmount }}</span>
                        @endif
                    </div>
                    @if (filled($this->pixDescription ?? null))
                        <p class="erp-licenca-bloqueada__pix-desc">{{ $this->pixDescription }}</p>
                    @endif
                    @if (filled($this->pixQrDataUrl ?? null))
                        <img
                            class="erp-licenca-bloqueada__qr"
                            src="{{ $this->pixQrDataUrl }}"
                            alt="QR Code Pix"
                            width="128"
                            height="128"
                        >
                    @endif
                    <p class="erp-licenca-bloqueada__pix-hint">Escaneie o QR ou copie o código Pix.</p>
                    @if (filled($this->pixBrCode ?? null))
                        <div class="erp-licenca-bloqueada__copia">
                            <input
                                x-ref="pixInput"
                                class="erp-licenca-bloqueada__copia-input"
                                type="text"
                                readonly
                                value="{{ $this->pixBrCode }}"
                                aria-label="Código Pix copia e cola"
                            >
                            <button
                                type="button"
                                class="erp-licenca-bloqueada__btn erp-licenca-bloqueada__btn--ghost erp-licenca-bloqueada__btn--copy"
                                @click="copyPix(@js($this->pixBrCode))"
                            >
                                <span x-show="!copied">Copiar</span>
                                <span x-show="copied" x-cloak>Copiado</span>
                            </button>
                        </div>
                    @endif
                </div>
            @elseif (filled($this->pixMessage ?? null))
                <p class="erp-licenca-bloqueada__feedback">{{ $this->pixMessage }}</p>
            @endif
        @endif

        @if (filled($this->feedback))
            <p class="erp-licenca-bloqueada__feedback">{{ $this->feedback }}</p>
        @endif

        <div class="erp-licenca-bloqueada__actions">
            <button
                type="button"
                class="erp-licenca-bloqueada__btn erp-licenca-bloqueada__btn--accent"
                wire:click="verificarNovamente"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="verificarNovamente">Verificar liberação</span>
                <span wire:loading wire:target="verificarNovamente">Verificando…</span>
            </button>

            @if (filled($this->pagamentoUrl))
                <a
                    href="{{ $this->pagamentoUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="erp-licenca-bloqueada__btn erp-licenca-bloqueada__btn--ghost"
                >Abrir portal</a>
            @endif

            <button
                type="button"
                class="erp-licenca-bloqueada__btn erp-licenca-bloqueada__btn--ghost"
                wire:click="sair"
            >Sair</button>
        </div>
    </div>
</div>
