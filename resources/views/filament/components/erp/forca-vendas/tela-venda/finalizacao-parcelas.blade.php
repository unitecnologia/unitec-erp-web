{{--
  Overlay sempre no DOM da finalização.
  Visibilidade via Alpine + $wire.fvTabelaPrazoConsulta (sincroniza mesmo com skipRender).
  Grade de parcelas via x-for em $wire.fvParcelasRows — evita remorph da Tela de Venda ao abrir (atalho B).
--}}
<div
    class="erp-pdv-parcelas-overlay"
    role="dialog"
    aria-labelledby="erp-fv-parcelas-title"
    x-show="$wire.fvTabelaPrazoConsulta"
    x-cloak
>
    <div class="erp-pdv-parcelas">
        <header class="erp-pdv-parcelas__header">
            <h3 id="erp-fv-parcelas-title">Contas Receber | Parcelas</h3>
            <button type="button" class="erp-pdv-modal__close" wire:click="cancelarFvTabelaPrazoConsulta" title="Fechar">✕</button>
        </header>

        <div class="erp-pdv-parcelas__toolbar">
            <div class="erp-pdv-parcelas__avulso">
                <span class="erp-pdv-parcelas__avulso-title">Avulso</span>
                <div class="erp-pdv-parcelas__avulso-fields">
                    <label class="erp-pdv-parcelas__field">
                        <span>Total</span>
                        <input
                            id="erp-fv-parcelas-total"
                            type="text"
                            x-bind:value="$wire.fvParcelasModalTotal"
                            readonly
                            tabindex="-1"
                        >
                    </label>
                    <label class="erp-pdv-parcelas__field">
                        <span>Parcelas</span>
                        <input
                            id="erp-fv-parcelas-qtd"
                            type="text"
                            wire:model="fvParcelasQtd"
                            data-mask="integer"
                            autocomplete="off"
                            x-bind:readonly="$wire.fvParcelasUiTravado"
                            x-bind:tabindex="$wire.fvParcelasUiTravado ? -1 : 0"
                        >
                    </label>
                    <label class="erp-pdv-parcelas__field">
                        <span>Intervalo</span>
                        <input
                            id="erp-fv-parcelas-intervalo"
                            type="text"
                            wire:model="fvParcelasIntervalo"
                            data-mask="integer"
                            autocomplete="off"
                            x-bind:readonly="$wire.fvParcelasUiTravado"
                            x-bind:tabindex="$wire.fvParcelasUiTravado ? -1 : 0"
                        >
                    </label>
                    <button
                        type="button"
                        class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                        wire:click="gerarFvParcelasCrediario"
                        x-bind:disabled="$wire.fvParcelasUiTravado"
                    >
                        <kbd>F2</kbd> Gerar
                    </button>
                </div>
            </div>

            <div class="erp-pdv-parcelas__toolbar-actions">
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--info"
                    wire:click="abrirFvTabelasPrazoPredefinidas"
                    x-show="$wire.fvParcelasPodeTabela"
                    x-cloak
                >
                    <kbd>F8</kbd> Tabelas
                </button>
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--danger"
                    wire:click="excluirFvParcelaCrediario"
                    x-bind:disabled="$wire.fvParcelasUiTravado"
                >
                    <kbd>F3</kbd> Excluir
                </button>
                <button type="button" class="erp-pdv-modal__btn" wire:click="cancelarFvTabelaPrazoConsulta">
                    <kbd>F4</kbd> Cancelar
                </button>
            </div>
        </div>

        <div
            class="erp-pdv-parcelas__tabelas"
            id="erp-fv-parcelas-tabelas"
            x-show="$wire.fvParcelasPodeTabela && $wire.fvTabelasPrazoListaAberta"
            x-cloak
        >
            <div class="erp-pdv-parcelas__tabelas-head">
                <strong>Prazos pré-definidos</strong>
                <button type="button" class="erp-pdv-parcelas__tabelas-close" wire:click="fecharFvTabelasPrazoPredefinidas" title="Fechar">✕</button>
            </div>
            <div class="erp-pdv-parcelas__tabelas-list">
                <table class="erp-pdv__grid erp-pdv-parcelas__tabelas-grid">
                    <thead>
                        <tr>
                            <th>Dias</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(tabela, index) in ($wire.fvTabelasPrazoPredefinidas || [])" :key="'fv-tp-' + index + '-' + (tabela.tabela_prazo_id || 0)">
                            <tr
                                x-on:click="$wire.selectFvTabelaPredefinida(index)"
                                x-on:dblclick="$wire.aplicarFvTabelaPrazoPredefinida()"
                                :id="'erp-fv-tabela-predef-row-' + index"
                                :class="{
                                    'erp-pdv__grid-row': true,
                                    'erp-pdv__grid-row--selected': $wire.fvSelectedTabelaPredefinidaIndex === index
                                }"
                            >
                                <td x-text="tabela.label || tabela.dias || '—'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="erp-pdv-parcelas__tabelas-actions">
                <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="aplicarFvTabelaPrazoPredefinida">
                    Enter | Usar tabela
                </button>
                <button type="button" class="erp-pdv-modal__btn" wire:click="fecharFvTabelasPrazoPredefinidas">
                    ESC | Voltar
                </button>
            </div>
        </div>

        <div class="erp-pdv-parcelas__grid-wrap" id="erp-fv-finalizar-tabela-prazo">
            <table
                class="erp-pdv__grid erp-pdv-parcelas__grid"
                :class="{ 'erp-pdv-parcelas__grid--cheque': !!$wire.fvParcelasShowCheque }"
            >
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Vencimento</th>
                        <th class="erp-pdv__grid-col-num">Valor</th>
                        <th x-show="$wire.fvParcelasShowCheque" x-cloak>Nº Cheque</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in ($wire.fvParcelasRows || [])" :key="'fv-parcela-' + index + '-' + (row.documento || '')">
                        <tr
                            x-on:click="$wire.selectFvParcelaRow(index)"
                            :id="'erp-fv-finalizar-prazo-row-' + index"
                            :class="{
                                'erp-pdv__grid-row': true,
                                'erp-pdv__grid-row--selected': $wire.fvSelectedParcelaIndex === index
                            }"
                        >
                            <td x-text="row.documento || '—'"></td>
                            <td x-text="row.vencimento || '—'"></td>
                            <td class="erp-pdv__grid-col-num" x-text="'R$ ' + (row.valor || '0,00')"></td>
                            <td x-show="$wire.fvParcelasShowCheque" x-cloak x-on:click.stop>
                                <input
                                    type="text"
                                    class="erp-pdv-parcelas__cheque-input"
                                    :id="'erp-fv-parcela-cheque-' + index"
                                    :value="row.numero_cheque || ''"
                                    x-on:change="$wire.set('fvParcelasRows.' + index + '.numero_cheque', $event.target.value)"
                                    autocomplete="off"
                                    maxlength="40"
                                    placeholder="Nº"
                                >
                            </td>
                        </tr>
                    </template>
                    <tr class="erp-pdv__grid-empty" x-show="!($wire.fvParcelasRows || []).length" x-cloak>
                        <td :colspan="$wire.fvParcelasShowCheque ? 4 : 3">
                            Avulso: Parcelas/Intervalo + F2 | Gerar — ou F8 | Tabelas pré-definidas.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer class="erp-pdv-parcelas__footer">
            <div class="erp-pdv-parcelas__total">
                <span>Total Parcelas</span>
                <strong x-text="$wire.fvParcelasModalTotalParcelas !== '' ? ('R$ ' + $wire.fvParcelasModalTotalParcelas) : ''"></strong>
            </div>
            <div class="erp-pdv-parcelas__footer-actions">
                <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="concluirFvParcelasCrediario">
                    <kbd>F7</kbd> Concluir
                </button>
            </div>
        </footer>
    </div>
</div>

{{-- Resumo também via Alpine: concluir parcelas usa skipRender e não remorph. --}}
<div
    class="erp-pdv-finalizar__prazo-resumo"
    x-show="! $wire.fvTabelaPrazoConsulta && !!$wire.tabelaPrazoDias"
    x-cloak
>
    <span>Parcelas:</span>
    <strong x-text="$wire.tabelaPrazoDias"></strong>
</div>
