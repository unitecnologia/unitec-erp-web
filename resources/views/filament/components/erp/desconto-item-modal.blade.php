{{--
  Modal compartilhado Ctrl+D — Desconto / Acréscimo.
  Esperado: $open, $idPrefix, $confirmMethod, $closeMethod, $descricao,
  $base, $qtd, $tipo, $pctInicial, $reaisInicial, $modoReais
--}}
@if ($open)
    @php
        $idPrefix = $idPrefix ?? 'erp-desconto';
        $pctId = $idPrefix.'-pct';
        $reaisId = $idPrefix.'-reais';
        $confirmMethod = $confirmMethod ?? 'confirmarItemAjuste';
        $closeMethod = $closeMethod ?? 'fecharModalDescontoItem';
        $tipo = ($tipo ?? 'desconto') === 'acrescimo' ? 'acrescimo' : 'desconto';
        $modoReais = $modoReais ?? \App\Support\Erp\EmpresaParametros::descontoReaisItemModo();
        $cfg = [
            'tipo' => $tipo,
            'modoReais' => $modoReais,
            'base' => (float) ($base ?? 0),
            'qtd' => (float) ($qtd ?? 0),
            'pctInicial' => (string) ($pctInicial ?? '0,00'),
            'reaisInicial' => (string) ($reaisInicial ?? '0,00'),
            'lastField' => (string) ($lastField ?? 'percentual'),
            'confirmMethod' => $confirmMethod,
            'closeMethod' => $closeMethod,
            'pctId' => $pctId,
            'reaisId' => $reaisId,
        ];
    @endphp
    <div
        class="erp-pdv-modal erp-desconto-item-modal"
        role="dialog"
        aria-modal="true"
        aria-label="Desconto ou acréscimo no item"
        wire:key="erp-desconto-modal-{{ $idPrefix }}"
        x-data="erpDescontoItemModalFactory(@js($cfg))"
        x-on:keydown.escape.window.prevent="fechar()"
    >
        <div class="erp-pdv-modal__backdrop" x-on:click="fechar()"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small erp-pdv-desconto">
            <header class="erp-pdv-modal__header">
                <h2>Ctrl+D — Desconto / Acréscimo</h2>
            </header>
            <div class="erp-pdv-modal__body">
                @if (($descricao ?? '') !== '')
                    <p class="erp-pdv-modal__hint">{{ $descricao }}</p>
                @endif

                <div class="erp-pdv-desconto__seg" role="group" aria-label="Operação">
                    <button
                        type="button"
                        class="erp-pdv-desconto__seg-btn"
                        :class="{ 'erp-pdv-desconto__seg-btn--desconto-on': tipo === 'desconto' }"
                        x-on:click="setTipo('desconto')"
                    >− Desconto</button>
                    <button
                        type="button"
                        class="erp-pdv-desconto__seg-btn"
                        :class="{ 'erp-pdv-desconto__seg-btn--acrescimo-on': tipo === 'acrescimo' }"
                        x-on:click="setTipo('acrescimo')"
                    >+ Acréscimo</button>
                </div>

                <div class="erp-pdv-desconto__dual" role="group" aria-label="Percentual e valor">
                    <label class="erp-pdv-desconto__dual-field" for="{{ $pctId }}">
                        <span class="erp-pdv-desconto__dual-label">Percentual (%)</span>
                        <div class="erp-pdv-desconto__valor">
                            <span class="erp-pdv-desconto__valor-prefix">%</span>
                            <input
                                id="{{ $pctId }}"
                                type="text"
                                class="erp-pdv-modal__input erp-pdv-desconto__valor-input"
                                x-model="pctRaw"
                                x-on:input="onPctInput()"
                                x-on:keydown.enter.prevent.stop="onEnter($event)"
                                inputmode="decimal"
                                autocomplete="off"
                                data-erp-desconto-field="pct"
                            >
                        </div>
                    </label>
                    <label class="erp-pdv-desconto__dual-field" for="{{ $reaisId }}">
                        <span class="erp-pdv-desconto__dual-label">Valor (R$)</span>
                        <div class="erp-pdv-desconto__valor">
                            <span class="erp-pdv-desconto__valor-prefix">R$</span>
                            <input
                                id="{{ $reaisId }}"
                                type="text"
                                class="erp-pdv-modal__input erp-pdv-desconto__valor-input"
                                x-model="reaisRaw"
                                x-on:input="onReaisInput()"
                                x-on:keydown.enter.prevent.stop="onEnter($event)"
                                inputmode="decimal"
                                autocomplete="off"
                                data-erp-desconto-field="reais"
                            >
                        </div>
                    </label>
                </div>

                <div
                    class="erp-pdv-desconto__preview"
                    :class="{
                        'erp-pdv-desconto__preview--desconto': preview().ajusteLinha > 0.0001 && tipo === 'desconto',
                        'erp-pdv-desconto__preview--acrescimo': preview().ajusteLinha > 0.0001 && tipo === 'acrescimo',
                    }"
                >
                    <div class="erp-pdv-desconto__preview-row">
                        <span>Original</span>
                        <strong>R$ <span x-text="formatBr(preview().bruto)"></span></strong>
                    </div>
                    <div class="erp-pdv-desconto__preview-row">
                        <span x-text="tipo === 'acrescimo' ? 'Acréscimo' : 'Desconto'"></span>
                        <strong>R$ <span x-text="formatBr(preview().ajusteLinha)"></span></strong>
                    </div>
                    <div class="erp-pdv-desconto__preview-row">
                        <span>Preço unit.</span>
                        <span class="erp-pdv-desconto__preview-precos">
                            <span class="erp-pdv-desconto__preview-de">R$ <span x-text="formatBr(base)"></span></span>
                            <span class="erp-pdv-desconto__preview-seta">→</span>
                            <strong class="erp-pdv-desconto__preview-novo">R$ <span x-text="formatBr(preview().novoPreco)"></span></strong>
                        </span>
                    </div>
                    <div class="erp-pdv-desconto__preview-row erp-pdv-desconto__preview-row--total">
                        <span>Total final</span>
                        <strong>R$ <span x-text="formatBr(preview().total)"></span></strong>
                    </div>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" x-on:click="aplicar()">Aplicar</button>
                <button type="button" class="erp-pdv-modal__btn" x-on:click="fechar()">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
