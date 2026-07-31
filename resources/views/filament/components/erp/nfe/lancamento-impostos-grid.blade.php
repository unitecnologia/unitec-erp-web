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
        <div class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap erp-nfe-impostos__grid-wrap">
            <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid erp-nfe-impostos__grid">
                <thead>
                    <tr>
                        <th class="erp-nfe-impostos__col-cod">Cód.</th>
                        <th class="erp-nfe-impostos__col-info">Informações Adicionais do Produto</th>
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
                        <th class="erp-nfe-lancamento-modal__num">Valor COFINS</th>
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
                            @class(['erp-lookup-modal__row--selected' => $this->nfeSelectedRowIndex === $index])
                        >
                            <td class="erp-nfe-impostos__cod">{{ ltrim((string) ($row['codigo'] ?? ''), '0') ?: '—' }}</td>
                            <td class="erp-nfe-impostos__cell-info">
                                <input type="text" wire:model.blur="nfeModalRows.{{ $index }}.info_adicionais" wire:click.stop class="erp-nfe-lancamento-modal__cell-input" autocomplete="off">
                            </td>
                            <td class="erp-nfe-lancamento-modal__num erp-nfe-impostos__readonly">{{ $row['total'] ?? '0,00' }}</td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.seguro" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.frete" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.outros" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.desconto" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.aliq_ipi" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_ipi" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.base_icms" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.aliq_icms" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_icms" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.aliq_pis_icms" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_pis_icms" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.aliq_cofins_icms" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_cofins_icms" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.motivo_desoneracao" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.base_desoneracao" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.desc_desoneracao" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_desoneracao" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.class_trib" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.cst_ibs_cbs" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.v_ibs_mun" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.v_ibs_uf" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.v_cbs" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.bc_ibs" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.alq_cbs" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.alq_ibs_mun" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.alq_ibs_uf" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
