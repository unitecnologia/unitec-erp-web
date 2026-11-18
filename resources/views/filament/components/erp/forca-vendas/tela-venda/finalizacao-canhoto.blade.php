{{--
  Overlay Canhoto POS sempre no DOM da finalização.
  Visibilidade via Alpine + $wire.finalizarCartaoCanhotoAberta (skipRender sem remorph da TV).
  Grade via x-for em $wire.finalizarCartaoParcelasRows.
--}}
<div
    class="erp-pdv-canhoto-overlay"
    role="dialog"
    aria-labelledby="erp-fv-canhoto-title"
    x-show="$wire.finalizarCartaoCanhotoAberta"
    x-cloak
>
    <div class="erp-pdv-parcelas erp-pdv-canhoto">
        <header class="erp-pdv-parcelas__header">
            <h3 id="erp-fv-canhoto-title">Canhoto POS</h3>
            <button type="button" class="erp-pdv-modal__close" wire:click="cancelFinalizarCartaoCanhoto" title="Fechar">✕</button>
        </header>

        <div class="erp-pdv-parcelas__toolbar">
            <div class="erp-pdv-parcelas__avulso">
                <span class="erp-pdv-parcelas__avulso-title">Dados do canhoto</span>
                <div class="erp-pdv-parcelas__avulso-fields">
                    <label class="erp-pdv-parcelas__field">
                        <span>Total</span>
                        <input
                            type="text"
                            id="erp-fv-canhoto-total"
                            x-bind:value="$wire.fvCanhotoTotalLabel"
                            readonly
                            tabindex="-1"
                        >
                    </label>
                    <label class="erp-pdv-parcelas__field">
                        <span>NSU</span>
                        <input id="erp-fv-canhoto-nsu" type="text" wire:model="finalizarCartaoNsu" autocomplete="off">
                    </label>
                    <label class="erp-pdv-parcelas__field">
                        <span>Autorização</span>
                        <input id="erp-fv-canhoto-autorizacao" type="text" wire:model="finalizarCartaoAutorizacao" autocomplete="off">
                    </label>
                    <label class="erp-pdv-parcelas__field erp-pdv-parcelas__field--bandeira">
                        <span>Maquininha</span>
                        <select id="erp-fv-canhoto-maquininha" wire:model="finalizarCartaoMaquininha">
                            <option value="">— Selecione —</option>
                            @foreach ($this->fvCanhotoMaquininhas as $nome)
                                <option value="{{ $nome }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-pdv-parcelas__field erp-pdv-parcelas__field--bandeira">
                        <span>Bandeira</span>
                        <select id="erp-fv-canhoto-bandeira" wire:model="finalizarCartaoBandeira">
                            <option value="">— Selecione —</option>
                            @foreach ($this->fvCanhotoBandeiras as $nome)
                                <option value="{{ $nome }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="erp-pdv-parcelas__avulso-fields" style="margin-top:0.5rem;">
                    <template x-if="$wire.fvCanhotoOcultarParcelas">
                        <p class="erp-pdv-pix-qr__hint" style="margin:0">À vista (1x) — informe NSU/autorização e conclua.</p>
                    </template>
                    <template x-if="! $wire.fvCanhotoOcultarParcelas">
                        <div class="erp-pdv-parcelas__avulso-fields" style="margin:0; padding:0; border:0;">
                            <label class="erp-pdv-parcelas__field">
                                <span>Qtd. Parcelas</span>
                                <input id="erp-fv-canhoto-qtd" type="text" wire:model="finalizarCartaoParcelasQtd" data-mask="integer" inputmode="numeric" autocomplete="off">
                            </label>
                            <label class="erp-pdv-parcelas__field">
                                <span>Intervalo (dias)</span>
                                <input id="erp-fv-canhoto-intervalo" type="text" wire:model="finalizarCartaoIntervalo" data-mask="integer" inputmode="numeric" autocomplete="off">
                            </label>
                            <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="gerarParcelasCartaoCanhoto">
                                <kbd>F2</kbd> Gerar
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="erp-pdv-parcelas__toolbar-actions">
                <button type="button" class="erp-pdv-modal__btn" wire:click="cancelFinalizarCartaoCanhoto">
                    <kbd>Esc</kbd> Cancelar
                </button>
            </div>
        </div>

        <div class="erp-pdv-parcelas__grid-wrap" id="erp-fv-finalizar-cartao-canhoto">
            <table class="erp-pdv__grid erp-pdv-parcelas__grid">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Vencimento</th>
                        <th class="erp-pdv__grid-col-num">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in ($wire.finalizarCartaoParcelasRows || [])" :key="'fv-cartao-parcela-'+index">
                        <tr
                            x-on:click="$wire.selectFinalizarCartaoParcelaRow(index)"
                            x-bind:id="'erp-fv-finalizar-canhoto-row-'+index"
                            x-bind:class="{
                                'erp-pdv__grid-row': true,
                                'erp-pdv__grid-row--selected': Number($wire.selectedFinalizarCartaoParcelaIndex) === Number(index),
                            }"
                        >
                            <td x-text="row.documento || '—'"></td>
                            <td x-text="row.vencimento || '—'"></td>
                            <td class="erp-pdv__grid-col-num" x-text="'R$ '+(row.valor || '0,00')"></td>
                        </tr>
                    </template>
                    <tr class="erp-pdv__grid-empty" x-show="!($wire.finalizarCartaoParcelasRows || []).length" x-cloak>
                        <td colspan="3" x-text="$wire.fvCanhotoOcultarParcelas
                            ? 'À vista (1x) — confirme com F7.'
                            : 'Informe NSU/Autorização/Bandeira e use F2 | Gerar parcelas.'"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer class="erp-pdv-parcelas__footer">
            <div class="erp-pdv-parcelas__total">
                <span>Total Parcelas</span>
                <strong x-text="$wire.fvCanhotoParcelasTotalLabel !== '' ? ('R$ '+$wire.fvCanhotoParcelasTotalLabel) : ''"></strong>
            </div>
            <div class="erp-pdv-parcelas__footer-actions">
                <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="concluirCartaoCanhoto">
                    <kbd>F7</kbd> Concluir
                </button>
            </div>
        </footer>
    </div>
</div>
