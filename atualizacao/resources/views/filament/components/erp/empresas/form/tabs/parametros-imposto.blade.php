{{-- Imposto padrão — mesmo layout da aba Impostos do produto --}}
<div class="erp-produtos-impostos erp-empresas-impostos">
    <div class="erp-produtos-impostos__row">
        <fieldset class="erp-produtos-impostos__group" title="Tributação ICMS para operações dentro do estado">
            <legend class="erp-produtos-impostos__legend">ICMS Interno</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="emp-icms-int-cfop">CFOP</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-icms-int-cfop" type="text" wire:model="data.param_imp_cfop_venda" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_cfop_venda', 'label' => 'CFOP (ICMS Interno)'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-icms-int-origem">Origem</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-icms-int-origem" type="text" wire:model="data.param_imp_origem" data-mask="integer" maxlength="1" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_origem', 'label' => 'Origem'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-icms-int-cst">CST</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-icms-int-cst" type="text" wire:model="data.param_imp_icms_cst" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_icms_cst', 'label' => 'CST (ICMS Interno)'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label
                        for="emp-icms-int-csosn"
                        class="erp-produtos-impostos__label--hint"
                        title="CSOSN — Código de Situação da Operação (Simples Nacional). Usado no lugar do CST quando a empresa é do Simples."
                    >CSOSN</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input
                            id="emp-icms-int-csosn"
                            type="text"
                            wire:model="data.param_imp_csosn"
                            maxlength="3"
                            class="erp-pcad-form__input erp-pcad-form__input--tax-xs"
                            title="CSOSN — Código de Situação da Operação (Simples Nacional). Usado no lugar do CST quando a empresa é do Simples."
                        >
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_csosn', 'label' => 'CSOSN (ICMS Interno)'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-icms-int-aliq">Alíq. %</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-icms-int-aliq" type="text" wire:model="data.param_imp_icms_aliquota" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_icms_aliquota', 'label' => 'Alíq. % (ICMS Interno)'])
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="erp-produtos-impostos__group" title="Tributação ICMS para operações interestaduais">
            <legend class="erp-produtos-impostos__legend">ICMS Externo</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="emp-icms-ext-cfop">CFOP</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-icms-ext-cfop" type="text" wire:model="data.param_imp_cfop_externo" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_cfop_externo', 'label' => 'CFOP (ICMS Externo)'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-icms-ext-cst">CST</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-icms-ext-cst" type="text" wire:model="data.param_imp_icms_cst_externo" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_icms_cst_externo', 'label' => 'CST (ICMS Externo)'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label
                        for="emp-icms-ext-csosn"
                        class="erp-produtos-impostos__label--hint"
                        title="CSOSN — Código de Situação da Operação (Simples Nacional) para venda interestadual."
                    >CSOSN</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input
                            id="emp-icms-ext-csosn"
                            type="text"
                            wire:model="data.param_imp_csosn_externo"
                            maxlength="3"
                            class="erp-pcad-form__input erp-pcad-form__input--tax-xs"
                            title="CSOSN — Código de Situação da Operação (Simples Nacional) para venda interestadual."
                        >
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_csosn_externo', 'label' => 'CSOSN (ICMS Externo)'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-icms-ext-aliq">Alíq. %</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-icms-ext-aliq" type="text" wire:model="data.param_imp_icms_aliquota_externo" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_icms_aliquota_externo', 'label' => 'Alíq. % (ICMS Externo)'])
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="erp-produtos-impostos__group" title="Contribuições PIS e COFINS na entrada e na saída">
            <legend class="erp-produtos-impostos__legend">PIS/COFINS</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="emp-pis-cst-entrada">CST Ent.</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-pis-cst-entrada" type="text" wire:model="data.param_imp_pis_cst" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_pis_cst', 'label' => 'CST Ent. (PIS)'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-pis-cst-saida">CST Saída</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-pis-cst-saida" type="text" wire:model="data.param_imp_cofins_cst" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_cofins_cst', 'label' => 'CST Saída'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-cst-cofins">CST COFINS</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-cst-cofins" type="text" wire:model="data.param_imp_cst_cofins" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_cst_cofins', 'label' => 'CST COFINS'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-pis-aliq">PIS %</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-pis-aliq" type="text" wire:model="data.param_imp_pis_aliquota" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_pis_aliquota', 'label' => 'PIS %'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-cofins-aliq">COFINS %</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-cofins-aliq" type="text" wire:model="data.param_imp_cofins_aliquota" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_cofins_aliquota', 'label' => 'COFINS %'])
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="erp-produtos-impostos__group" title="Imposto sobre Produtos Industrializados">
            <legend class="erp-produtos-impostos__legend">IPI</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="emp-ipi-cst">CST</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-ipi-cst" type="text" wire:model="data.param_imp_ipi_cst" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_ipi_cst', 'label' => 'CST (IPI)'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-ipi-aliq">Alíquota</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-ipi-aliq" type="text" wire:model="data.param_imp_ipi_aliquota" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_ipi_aliquota', 'label' => 'Alíquota (IPI)'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-ipi-enq">Cód. Enq.</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-ipi-enq" type="text" wire:model="data.param_imp_cod_enq_ipi" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_cod_enq_ipi', 'label' => 'Cód. Enq. (IPI)'])
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="erp-produtos-impostos__group" title="FCP, MVA, redução de base e benefício fiscal">
            <legend class="erp-produtos-impostos__legend">Outros</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="emp-outros-fcp">% FCP</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-outros-fcp" type="text" wire:model="data.param_imp_fcp_pct" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_fcp_pct', 'label' => '% FCP'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-outros-mva">% MVA</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-outros-mva" type="text" wire:model="data.param_imp_mva_pct" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_mva_pct', 'label' => '% MVA'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-outros-mva-normal">% MVA N.</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-outros-mva-normal" type="text" wire:model="data.param_imp_mva_normal" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_mva_normal', 'label' => '% MVA N.'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-outros-base">% Base Red.</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-outros-base" type="text" wire:model="data.param_imp_reducao_base_pct" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_reducao_base_pct', 'label' => '% Base Red.'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label
                        for="emp-cbenef"
                        class="erp-produtos-impostos__label--hint"
                        title="Código de Benefício Fiscal (cBenef) — informar quando houver benefício estadual na NF-e/NFC-e."
                    >Cód. Benef.</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input
                            id="emp-cbenef"
                            type="text"
                            wire:model="data.param_imp_cod_beneficio"
                            class="erp-pcad-form__input erp-produtos-impostos__cbenef-input"
                            title="Código de Benefício Fiscal (cBenef) — informar quando houver benefício estadual na NF-e/NFC-e."
                        >
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_cod_beneficio', 'label' => 'Cód. Benef.'])
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="erp-produtos-impostos__group" title="Diferimento, desoneração e tipo de tributação">
            <legend class="erp-produtos-impostos__legend">Fiscal Avançado</legend>
            <div class="erp-produtos-impostos__fields">
                <div class="erp-produtos-impostos__field">
                    <label for="emp-tipo-tributacao">Tipo Trib.</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-tipo-tributacao" type="text" wire:model="data.param_imp_tipo_tributacao" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_tipo_tributacao', 'label' => 'Tipo Trib.'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-icms-diferido">ICMS Dif.</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-icms-diferido" type="text" wire:model="data.param_imp_icms_diferido" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_icms_diferido', 'label' => 'ICMS Dif.'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label for="emp-aliq-deson">Alíq. Deson.</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input id="emp-aliq-deson" type="text" wire:model="data.param_imp_aliq_deson" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_aliq_deson', 'label' => 'Alíq. Deson.'])
                    </div>
                </div>
                <div class="erp-produtos-impostos__field">
                    <label
                        for="emp-motivo-deson"
                        class="erp-produtos-impostos__label--hint"
                        title="Motivo da Desoneração do ICMS (motDesICMS) — código da tabela SEFAZ (ex.: 1=táxi, 3=produtor agropecuário, 9=outros)."
                    >Mot. Deson.</label>
                    <div class="erp-empresas-impostos__input-row">
                        <input
                            id="emp-motivo-deson"
                            type="text"
                            wire:model="data.param_imp_motivo_desoneracao"
                            data-mask="integer"
                            class="erp-pcad-form__input erp-pcad-form__input--tax-sm"
                            title="Motivo da Desoneração do ICMS (motDesICMS) — código da tabela SEFAZ (ex.: 1=táxi, 3=produtor agropecuário, 9=outros)."
                        >
                        @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_motivo_desoneracao', 'label' => 'Mot. Deson.'])
                    </div>
                </div>
            </div>
        </fieldset>
    </div>

    <fieldset class="erp-produtos-impostos__group erp-produtos-impostos__group--iva" title="IVA / IBS / CBS — reforma tributária">
        <legend class="erp-produtos-impostos__legend">IVA</legend>
        <div class="erp-produtos-impostos__iva">
            <div class="erp-produtos-impostos__iva-cols">
                <div class="erp-produtos-impostos__fields">
                    <div class="erp-produtos-impostos__field">
                        <label for="emp-iva-cst">CST IBS/CBS</label>
                        <div class="erp-empresas-impostos__input-row">
                            <input id="emp-iva-cst" type="text" wire:model="data.param_imp_iva_cst" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                            @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_iva_cst', 'label' => 'CST IBS/CBS'])
                        </div>
                    </div>
                    <div class="erp-produtos-impostos__field erp-produtos-impostos__field--class-trib">
                        <label for="emp-cclass-trib">Classificação Tributária</label>
                        <div class="erp-produtos-impostos__cclass-wrap">
                            <input id="emp-cclass-trib" type="text" wire:model="data.param_imp_cclass_trib" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-sm erp-produtos-impostos__cclass-input">
                            <button
                                type="button"
                                class="erp-pcad-form__btn erp-produtos-impostos__cclass-lupa"
                                wire:click="openCclassTribModal"
                                title="Consultar Classificação Tributária IVA"
                            >
                                <span class="erp-pcad-form__btn-icon">🔍</span>
                            </button>
                            @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_cclass_trib', 'label' => 'Classificação Tributária'])
                        </div>
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="emp-aliq-ibs-uf">Aliq IBS UF</label>
                        <div class="erp-empresas-impostos__input-row">
                            <input id="emp-aliq-ibs-uf" type="text" wire:model="data.param_imp_aliq_ibs_uf" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                            @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_aliq_ibs_uf', 'label' => 'Aliq IBS UF'])
                        </div>
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="emp-aliq-cbs">Aliq CBS</label>
                        <div class="erp-empresas-impostos__input-row">
                            <input id="emp-aliq-cbs" type="text" wire:model="data.param_imp_aliq_cbs" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                            @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_aliq_cbs', 'label' => 'Aliq CBS'])
                        </div>
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="emp-aliq-ibs-mun">Aliq IBS Mun</label>
                        <div class="erp-empresas-impostos__input-row">
                            <input id="emp-aliq-ibs-mun" type="text" wire:model="data.param_imp_aliq_ibs_mun" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                            @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_aliq_ibs_mun', 'label' => 'Aliq IBS Mun'])
                        </div>
                    </div>
                </div>
                <div class="erp-produtos-impostos__fields">
                    <div class="erp-produtos-impostos__field">
                        <label for="emp-aliq-adrem-ibs">Aliq Adrem IBS</label>
                        <div class="erp-empresas-impostos__input-row">
                            <input id="emp-aliq-adrem-ibs" type="text" wire:model="data.param_imp_aliq_adrem_ibs" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                            @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_aliq_adrem_ibs', 'label' => 'Aliq Adrem IBS'])
                        </div>
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="emp-aliq-adrem-cbs">Aliq Adrem CBS</label>
                        <div class="erp-empresas-impostos__input-row">
                            <input id="emp-aliq-adrem-cbs" type="text" wire:model="data.param_imp_aliq_adrem_cbs" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                            @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_aliq_adrem_cbs', 'label' => 'Aliq Adrem CBS'])
                        </div>
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="emp-reducao-cbs">Redução CBS</label>
                        <div class="erp-empresas-impostos__input-row">
                            <input id="emp-reducao-cbs" type="text" wire:model="data.param_imp_reducao_cbs" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                            @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_reducao_cbs', 'label' => 'Redução CBS'])
                        </div>
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="emp-reducao-ibs">Redução IBS</label>
                        <div class="erp-empresas-impostos__input-row">
                            <input id="emp-reducao-ibs" type="text" wire:model="data.param_imp_reducao_ibs" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                            @include('filament.components.erp.empresas.form.partials.imposto-apply-btn', ['field' => 'param_imp_reducao_ibs', 'label' => 'Redução IBS'])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </fieldset>
</div>

<div class="erp-empresas-parametros__imposto-extra">
    <div class="erp-empresas-parametros__field erp-empresas-parametros__field--cfop-compra">
        <label class="erp-pcad-form__label" for="emp-cfop-compra">CFOP Compra</label>
        <input
            id="emp-cfop-compra"
            type="text"
            wire:model="data.param_imp_cfop_compra"
            maxlength="10"
            class="erp-pcad-form__input erp-pcad-form__input--sm"
        >
    </div>

    <div class="erp-empresas-parametros__imposto-imports">
        <button
            type="button"
            class="erp-pcad-form__btn erp-empresas-parametros__import-btn"
            wire:click="openCclassTribModal"
            title="Consulta e importação da tabela cClassTrib"
        >
            <span class="erp-empresas-parametros__import-btn-inner">
                <svg class="erp-empresas-parametros__import-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span>Importar Classificação Tributária IVA</span>
            </span>
        </button>
        <div class="erp-empresas-parametros__import-meta" title="{{ $this->data['param_imp_cclass_trib_arquivo_nome'] ?? '' }}">
            @if (filled($this->data['param_imp_cclass_trib_arquivo_nome'] ?? null))
                {{ $this->data['param_imp_cclass_trib_arquivo_nome'] }}
                @if (filled($this->data['param_imp_cclass_trib_importado_em'] ?? null))
                    <span>· {{ $this->data['param_imp_cclass_trib_importado_em'] }}</span>
                @endif
            @else
                Tabela padrão do sistema — atualizar com CSV oficial
            @endif
        </div>

        <button
            type="button"
            class="erp-pcad-form__btn erp-empresas-parametros__import-btn"
            wire:click="openIpbtaxModal"
            title="Importação / atualização da tabela IBPT / IPBTAX (padrão do sistema)"
        >
            <span class="erp-empresas-parametros__import-btn-inner">
                <svg class="erp-empresas-parametros__import-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span>Importar IPBTAX</span>
            </span>
        </button>
        <div class="erp-empresas-parametros__import-meta" title="{{ $this->data['param_imp_ipbtax_arquivo_nome'] ?? '' }}">
            @if (filled($this->data['param_imp_ipbtax_arquivo_nome'] ?? null))
                {{ $this->data['param_imp_ipbtax_arquivo_nome'] }}
                @if (filled($this->data['param_imp_ipbtax_importado_em'] ?? null))
                    <span>· {{ $this->data['param_imp_ipbtax_importado_em'] }}</span>
                @endif
            @else
                Tabela padrão do sistema — atualizar com TabelaIBPTax.csv
            @endif
        </div>
    </div>
</div>

<div class="erp-empresas-parametros__obs-block">
    <label class="erp-pcad-form__label" for="param-imp-obs">Observação — Consulte seu contador</label>
    <textarea
        id="param-imp-obs"
        wire:model="data.param_imp_observacao"
        class="erp-empresas-pcad__textarea erp-empresas-parametros__obs"
        rows="4"
    ></textarea>
</div>
