@if ($this->boletoContaPickOpen)
@teleport('body')
    <div
        class="erp-pdv-modal erp-pdv-modal--centered"
        wire:key="boleto-pos-doc-pick"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-boleto-pos-doc-pick-title"
        wire:keydown.escape.window="closeBoletoContaPickModal"
        x-data
        x-init="$nextTick(() => document.getElementById('erp-boleto-pos-doc-gerar')?.focus())"
    >
        <div class="erp-pdv-modal__backdrop" wire:click="closeBoletoContaPickModal"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small" wire:click.stop>
            <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
                <h2 id="erp-boleto-pos-doc-pick-title">Emitir boletos</h2>
                <button type="button" class="erp-pdv-modal__close" wire:click="closeBoletoContaPickModal" title="Fechar">✕</button>
            </header>
            <div class="erp-pdv-modal__body">
                <p class="erp-pdv-modal__hint">
                    {{ count($this->boletoPosDocumentoContaIds) }}
                    {{ count($this->boletoPosDocumentoContaIds) === 1 ? 'título' : 'títulos' }}
                    em Contas a Receber. Após emitir, o banco fica travado neste boleto.
                </p>
                <label class="erp-pdv-finalizar__field" style="display:block; margin-top:0.75rem;">
                    <span class="erp-pdv-finalizar__label">Conta de cobrança</span>
                    <select
                        class="erp-pdv-finalizar__input"
                        wire:model="boletoContaPickSelectedId"
                        autofocus
                    >
                        @foreach ($this->boletoContaPickOptions as $opt)
                            <option value="{{ $opt['id'] }}">
                                {{ $opt['rotulo'] }}{{ ! empty($opt['padrao']) ? ' (padrão)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button
                    type="button"
                    id="erp-boleto-pos-doc-gerar"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                    wire:click="confirmarGerarBoletoComConta"
                    wire:loading.attr="disabled"
                    wire:target="confirmarGerarBoletoComConta"
                >
                    <span wire:loading.remove wire:target="confirmarGerarBoletoComConta">Gerar boleto</span>
                    <span wire:loading wire:target="confirmarGerarBoletoComConta">Gerando…</span>
                </button>
                <button
                    type="button"
                    class="erp-pdv-modal__btn"
                    wire:click="closeBoletoContaPickModal"
                    wire:loading.attr="disabled"
                    wire:target="confirmarGerarBoletoComConta"
                >
                    Cancelar
                </button>
            </footer>
        </div>
    </div>
@endteleport
@endif

{{-- Progresso de emissão (mesmo espírito Contas a Receber / processos). --}}
@teleport('body')
<div
    class="erp-boleto-emit-progress"
    wire:loading.class="is-visible"
    wire:target="confirmarGerarBoletoComConta"
    role="status"
    aria-live="polite"
    aria-busy="true"
    x-data="{
        step: 0,
        labels: [
            'Validando conta de cobrança…',
            'Autenticando no banco…',
            'Registrando boleto(s)…',
            'Confirmando retorno…'
        ],
        timer: null,
        start() {
            this.step = 0;
            clearInterval(this.timer);
            this.timer = setInterval(() => {
                if (this.step < this.labels.length - 1) this.step++;
            }, 900);
        },
        stop() {
            clearInterval(this.timer);
            this.timer = null;
        }
    }"
    x-init="
        const data = $data;
        const el = $el;
        const sync = () => {
            if (el.classList.contains('is-visible')) data.start();
            else data.stop();
        };
        sync();
        new MutationObserver(sync).observe(el, { attributes: true, attributeFilter: ['class'] });
    "
>
    <div class="erp-boleto-emit-progress__backdrop" aria-hidden="true"></div>
    <div class="erp-boleto-emit-progress__panel">
        <div class="erp-boleto-emit-progress__spinner" aria-hidden="true"></div>
        <p class="erp-boleto-emit-progress__title">Gerando boleto</p>
        <p class="erp-boleto-emit-progress__status" x-text="labels[step]"></p>
        <div class="erp-boleto-emit-progress__track" aria-hidden="true">
            <div
                class="erp-boleto-emit-progress__bar"
                :style="'width:' + (((step + 1) / labels.length) * 100) + '%'"
            ></div>
        </div>
        <ol class="erp-boleto-emit-progress__steps">
            <template x-for="(label, i) in labels" :key="i">
                <li
                    :class="{ 'is-active': step === i, 'is-done': step > i }"
                    x-text="label.replace('…','')"
                ></li>
            </template>
        </ol>
        <p class="erp-boleto-emit-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>
@endteleport

{{-- Mesmo overlay de Contas a Receber (padrão visual). --}}
@php
    $boletoSucessoVisivel = filled($this->boletoSucessoDetalhe)
        && ! ($this->boletoEnviarModalOpen ?? false);
@endphp
@teleport('body')
<div
    id="erp-boleto-pos-doc-sucesso-overlay"
    class="erp-boleto-sucesso-overlay erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--sucesso{{ $boletoSucessoVisivel ? ' is-visible' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-boleto-pos-doc-sucesso-title"
    aria-live="polite"
    aria-hidden="{{ $boletoSucessoVisivel ? 'false' : 'true' }}"
    style="display: {{ $boletoSucessoVisivel ? 'grid' : 'none' }};"
    wire:key="boleto-pos-doc-sucesso"
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">✓</div>

        <h2 id="erp-boleto-pos-doc-sucesso-title" class="erp-nfe-fiscal-overlay__title">
            BOLETO GERADO COM SUCESSO
        </h2>

        <p class="erp-nfe-fiscal-overlay__codigo">{{ $this->boletoSucessoDetalhe }}</p>

        <div class="erp-nfe-fiscal-overlay__actions erp-nfe-fiscal-overlay__actions--cce">
            <button
                type="button"
                wire:click="printBoletoSucesso"
                class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--print"
            >Imprimir</button>

            <button
                type="button"
                wire:click="openBoletoEnviarModal"
                class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--email"
            >Enviar</button>

            <button
                type="button"
                wire:click="acknowledgeBoletoSucessoOverlay"
                class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
            >Sair</button>
        </div>

        <p class="erp-nfe-fiscal-overlay__hint">O título já está registrado no banco. Você pode imprimir ou enviar ao cliente.</p>
    </div>
</div>
@endteleport

@include('filament.components.erp.receber.boleto-enviar-modal', ['boletoEnviarStandalone' => true])
