{{-- Progresso de emissão Ailos/Sicredi (mesmo padrão do pós-documento). --}}
@teleport('body')
<div
    class="erp-boleto-emit-progress"
    wire:loading.class="is-visible"
    wire:target="emitirBoletoApi,confirmarGerarBoletoComConta"
    role="status"
    aria-live="polite"
    aria-busy="true"
    x-data="{
        step: 0,
        labels: [
            'Validando conta de cobrança…',
            'Conectando à API do banco…',
            'Autenticando (token / login)…',
            'Registrando boleto…',
            'Confirmando retorno…'
        ],
        timer: null,
        start() {
            this.step = 0;
            clearInterval(this.timer);
            this.timer = setInterval(() => {
                if (this.step < this.labels.length - 1) this.step++;
            }, 850);
        },
        stop() {
            clearInterval(this.timer);
            this.timer = null;
            this.step = 0;
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
        <p class="erp-boleto-emit-progress__hint">Aguarde a conexão com o banco. Não feche esta tela.</p>
    </div>
</div>
@endteleport
