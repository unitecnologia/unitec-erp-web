@php
    $titulo = match ($this->status) {
        'sem_cnpj' => 'CNPJ não cadastrado',
        'nao_encontrado' => 'Cliente não encontrado',
        default => 'Sistema bloqueado',
    };

    $descricao = match ($this->status) {
        'sem_cnpj' => 'Informe o CNPJ da empresa no cadastro para validarmos a licença.',
        'nao_encontrado' => 'Este CNPJ não está no gerenciador de licenças da Unitec.',
        default => 'Pague o Pix abaixo. Assim que o portal confirmar, o sistema libera automaticamente.',
    };

    $valorLabel = filled($this->pixAmount)
        ? 'R$ '.number_format((float) str_replace(',', '.', $this->pixAmount), 2, ',', '.')
        : '';
@endphp

<div
    class="erp-licenca-bloqueada"
    @if ($this->pixReady && $this->status === 'bloqueado')
        wire:poll.15s="pollPagamento"
    @endif
>
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
                    <dt>Válido até</dt>
                    <dd>{{ \Illuminate\Support\Carbon::parse($this->validoAte)->format('d/m/Y') }}</dd>
                </div>
            @endif
        </dl>

        @if ($this->pixLoading)
            <div class="erp-licenca-bloqueada__pix-loading">Gerando QR Code Pix…</div>
        @elseif ($this->pixReady)
            <div class="erp-licenca-bloqueada__pix">
                <div class="erp-licenca-bloqueada__pix-head">
                    <strong>Pague com Pix</strong>
                    @if ($valorLabel !== '')
                        <span>{{ $valorLabel }}</span>
                    @endif
                </div>

                @if (filled($this->pixDescription))
                    <p class="erp-licenca-bloqueada__pix-desc">{{ $this->pixDescription }}</p>
                @endif

                @if (filled($this->pixQrCodeDataUrl))
                    <img
                        src="{{ $this->pixQrCodeDataUrl }}"
                        alt="QR Code Pix"
                        class="erp-licenca-bloqueada__qr"
                    >
                @endif

                <p class="erp-licenca-bloqueada__pix-hint">
                    Escaneie com o app do banco. A liberação é automática após a confirmação.
                </p>

                @if (filled($this->pixBrCode))
                    <div class="erp-licenca-bloqueada__copia" x-data="{ copied: false }">
                        <input
                            type="text"
                            readonly
                            class="erp-licenca-bloqueada__copia-input"
                            value="{{ $this->pixBrCode }}"
                            id="erp-licenca-pix-brcode"
                        >
                        <button
                            type="button"
                            class="erp-licenca-bloqueada__btn erp-licenca-bloqueada__btn--ghost erp-licenca-bloqueada__btn--copy"
                            x-on:click="
                                navigator.clipboard.writeText(document.getElementById('erp-licenca-pix-brcode').value);
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            "
                        >
                            <span x-text="copied ? 'Copiado!' : 'Copiar Pix'"></span>
                        </button>
                    </div>
                @endif
            </div>
        @endif

        @if (filled($this->feedback))
            <p class="erp-licenca-bloqueada__feedback">{{ $this->feedback }}</p>
        @endif

        <div class="erp-licenca-bloqueada__actions">
            @if (! $this->pixReady && filled($this->cnpj) && $this->status === 'bloqueado')
                <button
                    type="button"
                    class="erp-licenca-bloqueada__btn erp-licenca-bloqueada__btn--primary"
                    wire:click="carregarPix"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="carregarPix">Gerar QR Code Pix</span>
                    <span wire:loading wire:target="carregarPix">Gerando…</span>
                </button>
            @endif

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
                class="erp-licenca-bloqueada__btn erp-licenca-bloqueada__btn--accent"
                wire:click="verificarNovamente"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="verificarNovamente">Já paguei — verificar</span>
                <span wire:loading wire:target="verificarNovamente">Verificando…</span>
            </button>

            <button
                type="button"
                class="erp-licenca-bloqueada__btn erp-licenca-bloqueada__btn--ghost"
                wire:click="sair"
            >Sair</button>
        </div>
    </div>
</div>

<style>
.erp-licenca-bloqueada-page .fi-page-content,
.erp-licenca-bloqueada-page .fi-sc {
    padding: 0 !important;
}
.erp-licenca-bloqueada {
    min-height: calc(100dvh - 3rem);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: linear-gradient(160deg, #0f2744 0%, #1e3a5f 45%, #334155 100%);
}
.erp-licenca-bloqueada__card {
    width: min(26rem, 100%);
    padding: 1.15rem 1.2rem 1.1rem;
    border-radius: 14px;
    border: 1px solid rgb(148 163 184 / 28%);
    background: #f8fafc;
    box-shadow: 0 22px 48px rgb(2 6 23 / 35%);
}
.erp-licenca-bloqueada__badge {
    display: inline-flex;
    margin-bottom: 0.45rem;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.erp-licenca-bloqueada__title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #0f172a;
}
.erp-licenca-bloqueada__desc {
    margin: 0.35rem 0 0;
    font-size: 0.82rem;
    line-height: 1.4;
    color: #475569;
}
.erp-licenca-bloqueada__meta {
    display: grid;
    gap: 0.35rem;
    margin: 0.85rem 0 0;
    padding: 0.6rem 0.7rem;
    border-radius: 10px;
    background: #e8eef6;
    border: 1px solid #c7d5e8;
}
.erp-licenca-bloqueada__meta > div {
    display: grid;
    grid-template-columns: 5.2rem minmax(0, 1fr);
    gap: 0.35rem;
    align-items: baseline;
}
.erp-licenca-bloqueada__meta dt {
    margin: 0;
    font-size: 0.64rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
}
.erp-licenca-bloqueada__meta dd {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 700;
    color: #0f2744;
    word-break: break-word;
}
.erp-licenca-bloqueada__pix-loading {
    margin-top: 0.85rem;
    padding: 0.75rem;
    border-radius: 10px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    font-size: 0.8rem;
    font-weight: 700;
    text-align: center;
}
.erp-licenca-bloqueada__pix {
    margin-top: 0.85rem;
    padding: 0.75rem;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #cbd5e1;
    text-align: center;
}
.erp-licenca-bloqueada__pix-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.84rem;
    color: #0f172a;
}
.erp-licenca-bloqueada__pix-head span {
    font-weight: 800;
    color: #1d4ed8;
}
.erp-licenca-bloqueada__pix-desc {
    margin: 0.35rem 0 0;
    font-size: 0.72rem;
    color: #64748b;
}
.erp-licenca-bloqueada__qr {
    width: 11.5rem;
    height: 11.5rem;
    margin: 0.65rem auto 0.35rem;
    display: block;
    border-radius: 8px;
    background: #fff;
}
.erp-licenca-bloqueada__pix-hint {
    margin: 0;
    font-size: 0.7rem;
    color: #64748b;
}
.erp-licenca-bloqueada__copia {
    display: grid;
    gap: 0.35rem;
    margin-top: 0.55rem;
}
.erp-licenca-bloqueada__copia-input {
    width: 100%;
    min-height: 2rem;
    padding: 0.35rem 0.5rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    font-size: 0.68rem;
    color: #334155;
}
.erp-licenca-bloqueada__feedback {
    margin: 0.7rem 0 0;
    padding: 0.5rem 0.6rem;
    border-radius: 8px;
    background: #fff7ed;
    border: 1px solid #fdba74;
    color: #9a3412;
    font-size: 0.76rem;
    font-weight: 600;
}
.erp-licenca-bloqueada__actions {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-top: 0.85rem;
}
.erp-licenca-bloqueada__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.25rem;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    border: 1px solid transparent;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}
.erp-licenca-bloqueada__btn:disabled {
    opacity: 0.55;
    cursor: wait;
}
.erp-licenca-bloqueada__btn--primary {
    background: #0f172a;
    color: #fff;
}
.erp-licenca-bloqueada__btn--accent {
    background: linear-gradient(180deg, #3b82f6 0%, #1d4ed8 100%);
    border-color: #1e40af;
    color: #fff;
}
.erp-licenca-bloqueada__btn--ghost {
    background: #fff;
    border-color: #cbd5e1;
    color: #334155;
}
.erp-licenca-bloqueada__btn--copy {
    min-height: 1.9rem;
    font-size: 0.76rem;
}
</style>
