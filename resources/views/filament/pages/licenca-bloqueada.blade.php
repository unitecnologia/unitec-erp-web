<x-filament-panels::page>
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

        $vencimentoLabel = $this->mensalidadeVencida ? 'Vencimento' : 'Válido até';
    @endphp

    {{-- Teleporta para o body: evita containing-block 0x0 do Filament --}}
    <div x-data>
        <template x-teleport="body">
            <div
                class="erp-lb"
                style="position:fixed;inset:0;z-index:2147483000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.7rem;padding:1rem;box-sizing:border-box;overflow:hidden;background:radial-gradient(900px 420px at 50% 12%,rgba(59,130,246,.22),transparent 60%),linear-gradient(165deg,#0b1a2e 0%,#13233f 42%,#1e3a5f 100%);"
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
                            if (input) {
                                input.focus();
                                input.select();
                            }
                        }
                    }
                }"
            >
                <div style="color:rgba(226,232,240,.92);font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;text-align:center;">
                    {{ config('unitec.app_name') }}
                </div>

                <div style="width:min(23.5rem,calc(100vw - 1.5rem));max-height:calc(100dvh - 3.2rem);overflow:auto;padding:1rem 1.05rem .95rem;border-radius:16px;background:#fff;color:#0f172a;box-shadow:0 24px 48px rgba(2,6,23,.45);box-sizing:border-box;">
                    <div style="display:inline-flex;margin-bottom:.35rem;padding:.14rem .5rem;border-radius:999px;background:#fee2e2;color:#b91c1c;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;">
                        Licença
                    </div>
                    <h1 style="margin:0;font-size:1.2rem;font-weight:800;line-height:1.2;color:#0f172a;">{{ $titulo }}</h1>
                    <p style="margin:.35rem 0 0;font-size:.78rem;line-height:1.35;color:#64748b;">{{ $descricao }}</p>

                    <dl style="display:grid;gap:.28rem;margin:.75rem 0 0;padding:.55rem .65rem;border-radius:10px;background:#f1f5f9;border:1px solid #e2e8f0;">
                        @if (filled($this->nome))
                            <div style="display:grid;grid-template-columns:5rem minmax(0,1fr);gap:.35rem;align-items:baseline;">
                                <dt style="margin:0;font-size:.6rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">Cliente</dt>
                                <dd style="margin:0;font-size:.76rem;font-weight:700;color:#0f2744;word-break:break-word;">{{ $this->nome }}</dd>
                            </div>
                        @endif
                        @if (filled($this->cnpj))
                            <div style="display:grid;grid-template-columns:5rem minmax(0,1fr);gap:.35rem;align-items:baseline;">
                                <dt style="margin:0;font-size:.6rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">CNPJ</dt>
                                <dd style="margin:0;font-size:.76rem;font-weight:700;color:#0f2744;word-break:break-word;">{{ $this->cnpj }}</dd>
                            </div>
                        @endif
                        @if (filled($this->validoAte))
                            <div style="display:grid;grid-template-columns:5rem minmax(0,1fr);gap:.35rem;align-items:baseline;">
                                <dt style="margin:0;font-size:.6rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">{{ $vencimentoLabel }}</dt>
                                <dd style="margin:0;font-size:.76rem;font-weight:700;color:#0f2744;word-break:break-word;">{{ \Illuminate\Support\Carbon::parse($this->validoAte)->format('d/m/Y') }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($this->mensalidadeVencida)
                        @if ($this->pixLoading)
                            <p class="erp-lb__loading">Carregando Pix…</p>
                        @elseif (filled($this->pixQrDataUrl) || filled($this->pixBrCode))
                            <div class="erp-lb__pix">
                                <div class="erp-lb__pix-head">
                                    <strong>Pagar com Pix</strong>
                                    @if (filled($this->pixAmount))
                                        <span>{{ $this->pixAmount }}</span>
                                    @endif
                                </div>
                                @if (filled($this->pixDescription))
                                    <p class="erp-lb__pix-desc">{{ $this->pixDescription }}</p>
                                @endif
                                @if (filled($this->pixQrDataUrl))
                                    <img
                                        class="erp-lb__qr"
                                        src="{{ $this->pixQrDataUrl }}"
                                        alt="QR Code Pix"
                                        width="128"
                                        height="128"
                                    >
                                @endif
                                <p class="erp-lb__pix-hint">Escaneie o QR ou copie o código Pix.</p>
                                @if (filled($this->pixBrCode))
                                    <div class="erp-lb__copia">
                                        <input
                                            x-ref="pixInput"
                                            class="erp-lb__copia-input"
                                            type="text"
                                            readonly
                                            value="{{ $this->pixBrCode }}"
                                            aria-label="Código Pix copia e cola"
                                        >
                                        <button
                                            type="button"
                                            class="erp-lb__btn erp-lb__btn--ghost erp-lb__btn--copy"
                                            @click="copyPix(@js($this->pixBrCode))"
                                        >
                                            <span x-show="!copied">Copiar</span>
                                            <span x-show="copied" x-cloak>Copiado</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @elseif (filled($this->pixMessage))
                            <p class="erp-lb__feedback">{{ $this->pixMessage }}</p>
                        @endif
                    @endif

                    @if (filled($this->feedback))
                        <p class="erp-lb__feedback">{{ $this->feedback }}</p>
                    @endif

                    <div class="erp-lb__actions">
                        <button
                            type="button"
                            class="erp-lb__btn erp-lb__btn--accent"
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
                                class="erp-lb__btn erp-lb__btn--ghost"
                            >Abrir portal</a>
                        @endif

                        <button
                            type="button"
                            class="erp-lb__btn erp-lb__btn--ghost"
                            wire:click="sair"
                        >Sair</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <style>
        /* Esconde chrome do ERP nesta página */
        .fi-body:has(.erp-licenca-bloqueada-page) .erp-shell,
        .fi-body.erp-licenca-bloqueada-body .erp-shell { display: none !important; }
        .fi-body:has(.erp-licenca-bloqueada-page),
        .fi-body.erp-licenca-bloqueada-body { overflow: hidden !important; background: #0b1a2e !important; }
    </style>
</x-filament-panels::page>
