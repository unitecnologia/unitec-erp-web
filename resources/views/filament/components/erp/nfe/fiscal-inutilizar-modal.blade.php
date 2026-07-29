@if ($this->nfeInutilizarModalOpen)

    @php

        $minMotivo = \App\Support\Erp\Nfe\NfeInutilizacaoMotivo::MIN_LENGTH;

        $maxMotivo = \App\Support\Erp\Nfe\NfeInutilizacaoMotivo::MAX_LENGTH;

        $inutilLength = mb_strlen(trim($this->nfeInutilizarJustificativa), 'UTF-8');

    @endphp



    <div class="erp-lookup-modal erp-nfce-fiscal-modal erp-nfe-inutilizar-modal" wire:keydown.escape.window="closeNfeInutilizarModal">

        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeInutilizarModal"></div>



        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-inutilizar-modal-title">

            <div class="erp-lookup-modal__titlebar">

                <span id="erp-nfe-inutilizar-modal-title">Inutilizar numeração NF-e</span>

                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeInutilizarModal" title="Fechar">✕</button>

            </div>



            <div class="erp-lookup-modal__body">

                <p class="erp-nfce-fiscal-modal__hint">

                    Informe a faixa de numeração da NF-e (modelo 55) que será inutilizada na SEFAZ.

                </p>



                <div class="erp-nfce-fiscal-modal__grid">

                    <label class="erp-nfce-fiscal-modal__field">

                        <span>Série</span>

                        <input type="number" min="1" wire:model="nfeInutilizarSerie" class="erp-nfce-fiscal-modal__input">

                    </label>

                    <label class="erp-nfce-fiscal-modal__field">

                        <span>Nº inicial</span>

                        <input type="number" min="1" wire:model="nfeInutilizarNumeroIni" class="erp-nfce-fiscal-modal__input">

                    </label>

                    <label class="erp-nfce-fiscal-modal__field">

                        <span>Nº final</span>

                        <input type="number" min="1" wire:model="nfeInutilizarNumeroFim" class="erp-nfce-fiscal-modal__input" placeholder="Opcional">

                    </label>

                </div>



                <div class="erp-nfce-fiscal-modal__field-group">

                    <label class="erp-nfce-fiscal-modal__label" for="erp-nfe-inutilizar-justificativa">Justificativa</label>

                    <textarea

                        id="erp-nfe-inutilizar-justificativa"

                        wire:model.live.debounce.150ms="nfeInutilizarJustificativa"

                        wire:keydown.ctrl.enter.prevent="confirmInutilizarNfe"

                        class="erp-nfce-fiscal-modal__textarea"

                        rows="4"

                        maxlength="{{ $maxMotivo }}"

                        placeholder="Mínimo {{ $minMotivo }} caracteres"

                    ></textarea>

                    <p @class([

                        'erp-nfce-fiscal-modal__counter',

                        'erp-nfce-fiscal-modal__counter--ok' => $inutilLength >= $minMotivo,

                    ])>

                        {{ $inutilLength }}/{{ $maxMotivo }}

                        @if ($inutilLength < $minMotivo)

                            — faltam {{ $minMotivo - $inutilLength }} caracteres

                        @endif

                    </p>

                </div>

            </div>



            <div class="erp-lookup-modal__actions erp-pcad-actions">

                <button

                    type="button"

                    wire:click="confirmInutilizarNfe"

                    wire:loading.attr="disabled"

                    wire:target="confirmInutilizarNfe"

                    class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"

                    @disabled($inutilLength < $minMotivo)

                >

                    <span class="erp-pcad-actions__icon">✓</span>

                    <span wire:loading.remove wire:target="confirmInutilizarNfe" class="erp-pcad-actions__label">Inutilizar</span>

                    <span wire:loading wire:target="confirmInutilizarNfe" class="erp-pcad-actions__label">Inutilizando…</span>

                </button>

                <button type="button" wire:click="closeNfeInutilizarModal" class="erp-pcad-actions__btn">

                    <span class="erp-pcad-actions__icon">↩</span>

                    <span class="erp-pcad-actions__label">Voltar</span>

                </button>

            </div>

        </div>

    </div>

@endif

