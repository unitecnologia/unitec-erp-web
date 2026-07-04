@php
    $rows = $this->nfeModalRows ?? [];
@endphp

<p class="erp-nfe-lancamento-modal__grid-hint">
    Um registro por item lançado. Campos editáveis — valores padrão vêm do cadastro do produto.
</p>

@if ($rows === [])
    <div class="erp-nfe-lancamento-modal__panel">
        <p class="erp-nfe-lancamento-modal__panel-text">Nenhum item lançado. Inclua itens na aba Itens.</p>
    </div>
@else
    <section class="erp-nfe-lancamento-modal__impostos-section">
        <h3 class="erp-nfe-lancamento-modal__impostos-title">Valores / IPI</h3>
        <div class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap--wide">
            <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid erp-nfe-lancamento-modal__grid--impostos">
                <thead>
                    <tr>
                        <th>Cód.</th>
                        <th>Informações Adicionais do Produto</th>
                        <th class="erp-nfe-lancamento-modal__num">Total</th>
                        <th class="erp-nfe-lancamento-modal__num">Seguro</th>
                        <th class="erp-nfe-lancamento-modal__num">Frete</th>
                        <th class="erp-nfe-lancamento-modal__num">Outros</th>
                        <th class="erp-nfe-lancamento-modal__num">Desconto</th>
                        <th class="erp-nfe-lancamento-modal__num">Aliq.IPI</th>
                        <th class="erp-nfe-lancamento-modal__num">Valor IPI</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr
                            wire:key="nfe-imp-val-{{ $row['key'] ?? $index }}"
                            wire:click="selectNfeRow({{ $index }})"
                            @class(['erp-lookup-modal__row--selected' => $this->nfeSelectedRowIndex === $index])
                        >
                            <td class="erp-nfe-lancamento-modal__center">{{ ltrim((string) ($row['codigo'] ?? ''), '0') ?: '—' }}</td>
                            <td>
                                <input type="text" wire:model.blur="nfeModalRows.{{ $index }}.info_adicionais" wire:click.stop class="erp-nfe-lancamento-modal__cell-input" autocomplete="off">
                            </td>
                            <td class="erp-nfe-lancamento-modal__num">{{ $row['total'] ?? '0,00' }}</td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.seguro" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.frete" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.outros" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.desconto" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.aliq_ipi" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                            <td><input type="text" wire:model.blur="nfeModalRows.{{ $index }}.valor_ipi" wire:click.stop class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num" autocomplete="off"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="erp-nfe-lancamento-modal__impostos-section">
        <h3 class="erp-nfe-lancamento-modal__impostos-title">ICMS / PIS / COFINS / Desoneração</h3>
        <div class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap--wide">
            <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid erp-nfe-lancamento-modal__grid--impostos">
                <thead>
                    <tr>
                        <th class="erp-nfe-lancamento-modal__num">Base ICMS</th>
                        <th class="erp-nfe-lancamento-modal__num">Aliq.ICMS</th>
                        <th class="erp-nfe-lancamento-modal__num">Valor ICMS</th>
                        <th class="erp-nfe-lancamento-modal__num">Aliq.PIS</th>
                        <th class="erp-nfe-lancamento-modal__num">Valor PIS</th>
                        <th class="erp-nfe-lancamento-modal__num">Aliq.COF</th>
                        <th class="erp-nfe-lancamento-modal__num">Valor COFINS</th>
                        <th class="erp-nfe-lancamento-modal__center">Motivo da Desoneração</th>
                        <th class="erp-nfe-lancamento-modal__num">Base Deson</th>
                        <th class="erp-nfe-lancamento-modal__num">Desc. Deson</th>
                        <th class="erp-nfe-lancamento-modal__num">Valor Deson</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr
                            wire:key="nfe-imp-icms-{{ $row['key'] ?? $index }}"
                            wire:click="selectNfeRow({{ $index }})"
                            @class(['erp-lookup-modal__row--selected' => $this->nfeSelectedRowIndex === $index])
                        >
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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="erp-nfe-lancamento-modal__impostos-section">
        <h3 class="erp-nfe-lancamento-modal__impostos-title">Reforma tributária — IBS / CBS</h3>
        <div class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap--wide">
            <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid erp-nfe-lancamento-modal__grid--impostos">
                <thead>
                    <tr>
                        <th class="erp-nfe-lancamento-modal__center">Class Trib</th>
                        <th class="erp-nfe-lancamento-modal__center">CST IBS/CBS</th>
                        <th class="erp-nfe-lancamento-modal__num">V. IBS Mun</th>
                        <th class="erp-nfe-lancamento-modal__num">V. IBS UF</th>
                        <th class="erp-nfe-lancamento-modal__num">V. CBS</th>
                        <th class="erp-nfe-lancamento-modal__num">BC. IBS</th>
                        <th class="erp-nfe-lancamento-modal__num">%Alq. CBS</th>
                        <th class="erp-nfe-lancamento-modal__num">%Alq. IBS MUN</th>
                        <th class="erp-nfe-lancamento-modal__num">%Alq. IBS UF</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr
                            wire:key="nfe-imp-reform-{{ $row['key'] ?? $index }}"
                            wire:click="selectNfeRow({{ $index }})"
                            @class(['erp-lookup-modal__row--selected' => $this->nfeSelectedRowIndex === $index])
                        >
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
    </section>
@endif
