@if ($this->showEtiquetas)
    <div
        class="erp-balanca-etq"
        role="dialog"
        aria-modal="true"
        aria-labelledby="balanca-etq-title"
        wire:ignore.self
        wire:keydown.f5.window.prevent="salvarEtiquetas"
        x-on:keydown.escape.window="
            $event.stopImmediatePropagation();
            $event.preventDefault();
            $wire.closeEtiquetas();
        "
    >
        <div class="erp-balanca-etq__backdrop" wire:click="closeEtiquetas" aria-hidden="true"></div>

        <div class="erp-balanca-etq__dialog">
            <header class="erp-balanca-etq__titlebar">
                <span id="balanca-etq-title" class="erp-balanca-etq__title">Configuração de Etiquetas / Código de Barras</span>
                <button
                    type="button"
                    class="erp-balanca__close"
                    wire:click="closeEtiquetas"
                    title="ESC | Fechar"
                    aria-label="Fechar"
                >&times;</button>
            </header>

            <div class="erp-balanca-etq__body">
                @if (filled($this->etiquetaFeedbackMsg))
                    <div
                        class="erp-balanca__feedback erp-balanca__feedback--{{ $this->etiquetaFeedbackTipo }}"
                        role="status"
                        wire:key="balanca-etq-feedback-{{ md5($this->etiquetaFeedbackMsg.$this->etiquetaFeedbackTipo) }}"
                    >
                        <p class="erp-balanca__feedback-text">{{ $this->etiquetaFeedbackMsg }}</p>
                        <button type="button" class="erp-balanca__feedback-close" wire:click="dismissEtiquetaFeedback" aria-label="Fechar mensagem">&times;</button>
                    </div>
                @endif

                <p class="erp-balanca-etq__intro">
                    Padrões EAN de etiqueta de balança — sempre iniciam com o prefixo
                    <strong>{{ $this->etiquetaPrefixo }}</strong>.
                    Selecione o modelo ou ajuste prefixo e dígitos conforme a balança.
                </p>

                <div class="erp-balanca-etq__diagrams" role="list">
                    @foreach ($this->etiquetaDiagrams() as $diagram)
                        <button
                            type="button"
                            class="erp-balanca-etq__card {{ (int) $this->etiquetaModelo === (int) $diagram['modelo'] ? 'is-active' : '' }}"
                            wire:click="selectEtiquetaDiagram({{ (int) $diagram['modelo'] }})"
                            role="listitem"
                            title="Usar modelo {{ $diagram['title'] }}"
                        >
                            <span class="erp-balanca-etq__card-num">{{ $diagram['title'] }}</span>
                            <div class="erp-balanca-etq__barcode" aria-hidden="true">
                                @foreach ($diagram['parts'] as $part)
                                    <span class="erp-balanca-etq__seg erp-balanca-etq__seg--{{ $part['role'] }}">
                                        <span class="erp-balanca-etq__seg-val">{{ $part['v'] }}</span>
                                        @if (filled($part['cap']))
                                            <span class="erp-balanca-etq__seg-cap">{{ $part['cap'] }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                            <span class="erp-balanca-etq__card-meta">
                                {{ $diagram['digitos'] }} dig. · {{ $diagram['valor'] === 'total' ? 'Total' : 'Peso' }}
                            </span>
                        </button>
                    @endforeach
                </div>

                <div class="erp-balanca-etq__form">
                    <div class="erp-balanca__field">
                        <label class="erp-balanca__label" for="balanca-etq-modelo">Modelo</label>
                        <select
                            id="balanca-etq-modelo"
                            class="erp-balanca__select"
                            wire:model.live="etiquetaModelo"
                        >
                            @foreach ($this->etiquetaModeloOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="erp-balanca__field">
                        <label class="erp-balanca__label" for="balanca-etq-prefixo">Prefixo Cód.Barra</label>
                        <input
                            id="balanca-etq-prefixo"
                            type="text"
                            class="erp-balanca__input erp-balanca-etq__input--narrow"
                            wire:model.blur="etiquetaPrefixo"
                            maxlength="2"
                            inputmode="numeric"
                            spellcheck="false"
                        >
                    </div>

                    <div class="erp-balanca__field">
                        <label class="erp-balanca__label" for="balanca-etq-digitos">Dígitos</label>
                        <select
                            id="balanca-etq-digitos"
                            class="erp-balanca__select erp-balanca-etq__input--narrow"
                            wire:model.live="etiquetaDigitos"
                        >
                            <option value="4">4</option>
                            <option value="5">5</option>
                            <option value="6">6</option>
                        </select>
                    </div>
                </div>
            </div>

            <footer class="erp-balanca__footer erp-pcad-actions erp-pcad-actions--split">
                <div class="erp-balanca__footer-left">
                    <button
                        type="button"
                        class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                        data-erp-key="F5"
                        wire:click="salvarEtiquetas"
                        wire:loading.attr="disabled"
                        wire:target="salvarEtiquetas"
                    >
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                        <span class="erp-pcad-actions__label" wire:loading.remove wire:target="salvarEtiquetas"><kbd>F5</kbd> | Gravar</span>
                        <span class="erp-pcad-actions__label" wire:loading wire:target="salvarEtiquetas">Gravando…</span>
                    </button>
                </div>

                <button
                    type="button"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--danger"
                    data-erp-key="Escape"
                    wire:click="closeEtiquetas"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </footer>
        </div>
    </div>
@endif
