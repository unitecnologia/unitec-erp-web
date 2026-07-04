<div class="erp-produtos-impostos">
    <div class="erp-produtos-impostos__grid">
        <fieldset class="erp-produtos-impostos__group erp-produtos-impostos__group--icms-interno">
            <legend class="erp-produtos-impostos__legend">ICMS Interno</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-icms-int-cfop">CFOP</label>
                    <input id="pprod-icms-int-cfop" type="text" wire:model="data.cfop_interno" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-icms-int-origem">Origem</label>
                    <input id="pprod-icms-int-origem" type="text" wire:model="data.origem" data-mask="integer" maxlength="1" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-icms-int-cst">CST</label>
                    <input id="pprod-icms-int-cst" type="text" wire:model="data.cst_icms" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-icms-int-csosn">CSOSN</label>
                    <input id="pprod-icms-int-csosn" type="text" wire:model="data.csosn" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-icms-int-aliq">Alíq. %</label>
                    <input id="pprod-icms-int-aliq" type="text" wire:model="data.aliq_icms" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
            </div>
        </fieldset>

        <fieldset class="erp-produtos-impostos__group erp-produtos-impostos__group--icms-externo">
            <legend class="erp-produtos-impostos__legend">ICMS Externo</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-icms-ext-cfop">CFOP</label>
                    <input id="pprod-icms-ext-cfop" type="text" wire:model="data.cfop_externo" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-icms-ext-cst">CST</label>
                    <input id="pprod-icms-ext-cst" type="text" wire:model="data.cst_externo" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-icms-ext-csosn">CSOSN</label>
                    <input id="pprod-icms-ext-csosn" type="text" wire:model="data.csosn_externo" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-icms-ext-aliq">Alíq. %</label>
                    <input id="pprod-icms-ext-aliq" type="text" wire:model="data.aliq_icms_externo" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
            </div>
        </fieldset>

        <fieldset class="erp-produtos-impostos__group erp-produtos-impostos__group--pis-cofins">
            <legend class="erp-produtos-impostos__legend">PIS/COFINS</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-pis-cst-entrada">CST Entrada</label>
                    <input id="pprod-pis-cst-entrada" type="text" wire:model="data.cst_entrada" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-pis-cst-saida">CST Saída</label>
                    <input id="pprod-pis-cst-saida" type="text" wire:model="data.cst_saida" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-pis-aliq">Aliq. Pis %</label>
                    <input id="pprod-pis-aliq" type="text" wire:model="data.aliq_pis" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-cofins-aliq">Aliq. Cofins %</label>
                    <input id="pprod-cofins-aliq" type="text" wire:model="data.aliq_cofins" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
            </div>
        </fieldset>

        <div class="erp-produtos-impostos__ipi-row">
            <fieldset class="erp-produtos-impostos__group erp-produtos-impostos__group--ipi">
                <legend class="erp-produtos-impostos__legend">IPI</legend>
                <div class="erp-produtos-impostos__fields">
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-ipi-cst">CST</label>
                        <input id="pprod-ipi-cst" type="text" wire:model="data.cst_ipi" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-ipi-aliq">Alíquota</label>
                        <input id="pprod-ipi-aliq" type="text" wire:model="data.aliq_ipi" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-ipi-enq">Cód. Enq.</label>
                        <input id="pprod-ipi-enq" type="text" wire:model="data.cod_enq_ipi" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                </div>
            </fieldset>

            <fieldset class="erp-produtos-impostos__group erp-produtos-impostos__group--outros">
                <legend class="erp-produtos-impostos__legend">Outros</legend>
                <div class="erp-produtos-impostos__fields">
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-outros-fcp">% FCP</label>
                        <input id="pprod-outros-fcp" type="text" wire:model="data.fcp_pct" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-outros-mva">% MVA</label>
                        <input id="pprod-outros-mva" type="text" wire:model="data.mva_pct" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-outros-mva-normal">% MVA Normal</label>
                        <input id="pprod-outros-mva-normal" type="text" wire:model="data.mva_normal" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-outros-base">% Base Reduzida</label>
                        <input id="pprod-outros-base" type="text" wire:model="data.reducao_base_pct" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-cbenef">Cód. Benefício Fiscal</label>
                        <input id="pprod-cbenef" type="text" wire:model="data.cod_beneficio" class="erp-pcad-form__input erp-produtos-impostos__cbenef-input">
                    </div>
                </div>
            </fieldset>
        </div>
    </div>

    <div class="erp-produtos-impostos__fiscal-row">
        <fieldset class="erp-produtos-impostos__group erp-produtos-impostos__group--fiscal-avancado">
            <legend class="erp-produtos-impostos__legend">Fiscal Avançado</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-tipo-tributacao">Tipo Tributação</label>
                    <input id="pprod-tipo-tributacao" type="text" wire:model="data.tipo_tributacao" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-icms-diferido">ICMS Diferido</label>
                    <input id="pprod-icms-diferido" type="text" wire:model="data.icms_diferido" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-aliq-deson">Alíq. Desoneração</label>
                    <input id="pprod-aliq-deson" type="text" wire:model="data.aliq_deson" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-motivo-deson">Motivo Desoneração</label>
                    <input id="pprod-motivo-deson" type="text" wire:model="data.motivo_desoneracao" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
            </div>
        </fieldset>

        <fieldset class="erp-produtos-impostos__group erp-produtos-impostos__group--iva">
            <legend class="erp-produtos-impostos__legend">IVA</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-iva-cst">CST IVA</label>
                    <input id="pprod-iva-cst" type="text" wire:model="data.iva_cst" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-iva-aliq">Alíq. IVA</label>
                    <input id="pprod-iva-aliq" type="text" wire:model="data.iva_aliq" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-iva-red-base">Red. Base IVA</label>
                    <input id="pprod-iva-red-base" type="text" wire:model="data.iva_red_base" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="pprod-iva-classificacao">Classificação</label>
                    <input id="pprod-iva-classificacao" type="text" wire:model="data.iva_classificacao" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                </div>
            </div>
        </fieldset>
    </div>
</div>
