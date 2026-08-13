@php

    $rows = $this->nfeModalRows ?? [];

@endphp



<div class="erp-nfe-impostos">

    @if ($rows === [])

        <div class="erp-nfe-impostos__empty">

            <strong>Nenhum item lançado</strong>

            <span>Inclua produtos na aba Itens para editar os impostos.</span>

        </div>

    @else

        <div

            class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap erp-nfe-impostos__grid-wrap"

            tabindex="-1"

            wire:keydown.arrow-up.prevent="moveNfeSelectedRow(-1)"

            wire:keydown.arrow-down.prevent="moveNfeSelectedRow(1)"

            x-data

            @keydown.enter="

                const el = $event.target;

                if (! (el instanceof HTMLInputElement) || el.disabled) {

                    return;

                }

                if (! el.hasAttribute('data-erp-nfe-impostos-enter')) {

                    return;

                }



                $event.preventDefault();

                $event.stopPropagation();



                el.removeAttribute('readonly');



                const collect = () => Array.from($el.querySelectorAll(

                    'input[data-erp-nfe-impostos-enter]:not([disabled])'

                )).filter((field) => field.offsetParent !== null);



                const fields = collect();

                const idx = fields.indexOf(el);

                if (idx < 0) {

                    return;

                }



                const nextIdx = idx + 1 < fields.length ? idx + 1 : 0;



                el.blur();



                const tryFocus = (attempt = 0) => {

                    const fresh = collect();

                    const next = fresh[nextIdx] ?? null;

                    if (! next) {

                        return;

                    }



                    next.removeAttribute('readonly');

                    next.focus();



                    if (document.activeElement === next || attempt >= 12) {

                        if (! next.readOnly) {

                            next.select();

                        }

                        return;

                    }



                    setTimeout(() => tryFocus(attempt + 1), 30 + (attempt * 20));

                };



                setTimeout(() => tryFocus(0), 0);

            "

        >

            <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid erp-nfe-impostos__grid">

                <thead>

                    <tr>

                        <th class="erp-nfe-impostos__col-cod">Cód.</th>

                        <th class="erp-nfe-impostos__col-info" title="Informações Adicionais do Produto">Inf. adic.</th>

                        <th class="erp-nfe-lancamento-modal__num">Total</th>

                        <th class="erp-nfe-lancamento-modal__num">Seguro</th>

                        <th class="erp-nfe-lancamento-modal__num">Frete</th>

                        <th class="erp-nfe-lancamento-modal__num">Outros</th>

                        <th class="erp-nfe-lancamento-modal__num">Desconto</th>

                        <th class="erp-nfe-lancamento-modal__num">Aliq.IPI</th>

                        <th class="erp-nfe-lancamento-modal__num">Valor IPI</th>

                        <th class="erp-nfe-lancamento-modal__num">Base ICMS</th>

                        <th class="erp-nfe-lancamento-modal__num">Aliq. ICMS</th>

                        <th class="erp-nfe-lancamento-modal__num">Valor ICMS</th>

                        <th class="erp-nfe-lancamento-modal__num">Aliq. PIS</th>

                        <th class="erp-nfe-lancamento-modal__num">Valor PIS</th>

                        <th class="erp-nfe-lancamento-modal__num">Aliq. COF</th>

                        <th class="erp-nfe-lancamento-modal__num">Val. COFINS</th>

                        <th class="erp-nfe-lancamento-modal__center">Motivo deson.</th>

                        <th class="erp-nfe-lancamento-modal__num">Base deson.</th>

                        <th class="erp-nfe-lancamento-modal__num">Desc. deson.</th>

                        <th class="erp-nfe-lancamento-modal__num">Valor deson.</th>

                        <th class="erp-nfe-lancamento-modal__center">Class. trib.</th>

                        <th class="erp-nfe-lancamento-modal__center">CST IBS/CBS</th>

                        <th class="erp-nfe-lancamento-modal__num">V. IBS Mun</th>

                        <th class="erp-nfe-lancamento-modal__num">V. IBS UF</th>

                        <th class="erp-nfe-lancamento-modal__num">V. CBS</th>

                        <th class="erp-nfe-lancamento-modal__num">BC IBS</th>

                        <th class="erp-nfe-lancamento-modal__num">% Alíq. CBS</th>

                        <th class="erp-nfe-lancamento-modal__num">% Alíq. IBS Mun</th>

                        <th class="erp-nfe-lancamento-modal__num">% Alíq. IBS UF</th>

                    </tr>

                </thead>

                <tbody>

                    @foreach ($rows as $index => $row)

                        <tr

                            wire:key="nfe-imp-{{ $row['key'] ?? $index }}"

                            wire:click="selectNfeRow({{ $index }})"

                            x-on:click="
                                if ($event.target.closest('input, select, textarea, button')) return;
                                $nextTick(() => $el.closest('.erp-nfe-impostos__grid-wrap')?.focus({ preventScroll: true }))
                            "

                            data-erp-nfe-item-index="{{ $index }}"

                            @class(['erp-lookup-modal__row--selected' => $this->nfeSelectedRowIndex === $index])

                        >

                            <td class="erp-nfe-impostos__cod">{{ ltrim((string) ($row['codigo'] ?? ''), '0') ?: '—' }}</td>

                            <td class="erp-nfe-impostos__cell-info">
                                @php
                                    $infoText = trim((string) ($row['info_adicionais'] ?? ''));
                                    $infoFilled = $infoText !== '';
                                @endphp
                                <button
                                    type="button"
                                    wire:click.stop="abrirNfeInfoAdicionaisModal({{ $index }})"
                                    @class([
                                        'erp-nfe-impostos__info-btn',
                                        'erp-nfe-impostos__info-btn--filled' => $infoFilled,
                                    ])
                                    title="{{ $infoFilled ? $infoText : 'Informações adicionais do produto' }}"
                                >
                                    {{ $infoFilled ? 'Editar' : 'Digitar' }}
                                </button>
                            </td>

                            <td class="erp-nfe-lancamento-modal__num erp-nfe-impostos__readonly">{{ $row['total'] ?? '0,00' }}</td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.seguro" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.frete" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.outros" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.desconto" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.aliq_ipi" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_ipi" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.base_icms" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.aliq_icms" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_icms" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.aliq_pis_icms" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_pis_icms" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.aliq_cofins_icms" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_cofins_icms" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.motivo_desoneracao" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.base_desoneracao" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.desc_desoneracao" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_desoneracao" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.class_trib" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.cst_ibs_cbs" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.v_ibs_mun" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.v_ibs_uf" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.v_cbs" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.bc_ibs" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.alq_cbs" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.alq_ibs_mun" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.alq_ibs_uf" wire:click.stop data-erp-nfe-impostos-enter x-on:focus="$el.removeAttribute('readonly'); $el.select()" x-on:click.stop="$el.removeAttribute('readonly'); $el.select()" @mouseup.prevent class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    @endif

</div>

