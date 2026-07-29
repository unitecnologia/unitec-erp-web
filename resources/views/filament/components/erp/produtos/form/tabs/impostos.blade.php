<div class="erp-produtos-impostos">
    <div class="erp-produtos-impostos__toolbar">
        <span class="erp-produtos-impostos__toolbar-hint">
            Preenchido com o <strong>Imposto Padrão</strong> da empresa. Altere só o que for diferente neste produto.
        </span>
        <button
            type="button"
            class="erp-pcad-form__btn erp-produtos-impostos__reload-btn"
            wire:click="applyEmpresaImpostoPadraoToProductForm"
            title="Restaurar o Imposto Padrão da empresa (substitui os valores atuais desta aba)"
        >
            Restaurar padrão
        </button>
    </div>
    <div class="erp-produtos-impostos__row">
    <fieldset class="erp-produtos-impostos__group" title="Tributação ICMS para operações dentro do estado">
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
                <label
                    for="pprod-icms-int-csosn"
                    class="erp-produtos-impostos__label--hint"
                    title="CSOSN — Código de Situação da Operação (Simples Nacional). Usado no lugar do CST quando a empresa é do Simples."
                >CSOSN</label>
                <input
                    id="pprod-icms-int-csosn"
                    type="text"
                    wire:model="data.csosn"
                    maxlength="3"
                    class="erp-pcad-form__input erp-pcad-form__input--tax-xs"
                    title="CSOSN — Código de Situação da Operação (Simples Nacional). Usado no lugar do CST quando a empresa é do Simples."
                >
            </div>
            <div class="erp-produtos-impostos__field">
                <label for="pprod-icms-int-aliq">Alíq. %</label>
                <input id="pprod-icms-int-aliq" type="text" wire:model="data.aliq_icms" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
            </div>
        </div>
    </fieldset>

    <fieldset class="erp-produtos-impostos__group" title="Tributação ICMS para operações interestaduais">
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
                <label
                    for="pprod-icms-ext-csosn"
                    class="erp-produtos-impostos__label--hint"
                    title="CSOSN — Código de Situação da Operação (Simples Nacional) para venda interestadual."
                >CSOSN</label>
                <input
                    id="pprod-icms-ext-csosn"
                    type="text"
                    wire:model="data.csosn_externo"
                    maxlength="3"
                    class="erp-pcad-form__input erp-pcad-form__input--tax-xs"
                    title="CSOSN — Código de Situação da Operação (Simples Nacional) para venda interestadual."
                >
            </div>
            <div class="erp-produtos-impostos__field">
                <label for="pprod-icms-ext-aliq">Alíq. %</label>
                <input id="pprod-icms-ext-aliq" type="text" wire:model="data.aliq_icms_externo" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
            </div>
        </div>
    </fieldset>

    <fieldset class="erp-produtos-impostos__group" title="Contribuições PIS e COFINS na entrada e na saída">
        <legend class="erp-produtos-impostos__legend">PIS/COFINS</legend>
        <div class="erp-produtos-impostos__fields">
            <div class="erp-produtos-impostos__field">
                <label for="pprod-pis-cst-entrada">CST Ent.</label>
                <input id="pprod-pis-cst-entrada" type="text" wire:model="data.cst_entrada" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
            </div>
            <div class="erp-produtos-impostos__field">
                <label for="pprod-pis-cst-saida">CST Saída</label>
                <input id="pprod-pis-cst-saida" type="text" wire:model="data.cst_saida" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
            </div>
            <div class="erp-produtos-impostos__field">
                <label for="pprod-cst-cofins">CST COFINS</label>
                <input id="pprod-cst-cofins" type="text" wire:model="data.cst_cofins" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
            </div>
            <div class="erp-produtos-impostos__field">
                <label for="pprod-pis-aliq">PIS %</label>
                <input id="pprod-pis-aliq" type="text" wire:model="data.aliq_pis" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
            </div>
            <div class="erp-produtos-impostos__field">
                <label for="pprod-cofins-aliq">COFINS %</label>
                <input id="pprod-cofins-aliq" type="text" wire:model="data.aliq_cofins" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
            </div>
        </div>
    </fieldset>

    <fieldset class="erp-produtos-impostos__group" title="Imposto sobre Produtos Industrializados">
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

    <fieldset class="erp-produtos-impostos__group" title="FCP, MVA, redução de base e benefício fiscal">
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
                <label for="pprod-outros-mva-normal">% MVA N.</label>
                <input id="pprod-outros-mva-normal" type="text" wire:model="data.mva_normal" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
            </div>
            <div class="erp-produtos-impostos__field">
                <label for="pprod-outros-base">% Base Red.</label>
                <input id="pprod-outros-base" type="text" wire:model="data.reducao_base_pct" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
            </div>
            <div class="erp-produtos-impostos__field">
                <label
                    for="pprod-cbenef"
                    class="erp-produtos-impostos__label--hint"
                    title="Código de Benefício Fiscal (cBenef) — informar quando houver benefício estadual na NF-e/NFC-e."
                >Cód. Benef.</label>
                <input
                    id="pprod-cbenef"
                    type="text"
                    wire:model="data.cod_beneficio"
                    class="erp-pcad-form__input erp-produtos-impostos__cbenef-input"
                    title="Código de Benefício Fiscal (cBenef) — informar quando houver benefício estadual na NF-e/NFC-e."
                >
            </div>
        </div>
    </fieldset>

    <fieldset class="erp-produtos-impostos__group" title="Diferimento, desoneração e tipo de tributação">
        <legend class="erp-produtos-impostos__legend">Fiscal Avançado</legend>
        <div class="erp-produtos-impostos__fields">
            <div class="erp-produtos-impostos__field">
                <label for="pprod-tipo-tributacao">Tipo Trib.</label>
                <input id="pprod-tipo-tributacao" type="text" wire:model="data.tipo_tributacao" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
            </div>
            <div class="erp-produtos-impostos__field">
                <label for="pprod-icms-diferido">ICMS Dif.</label>
                <input id="pprod-icms-diferido" type="text" wire:model="data.icms_diferido" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
            </div>
            <div class="erp-produtos-impostos__field">
                <label for="pprod-aliq-deson">Alíq. Deson.</label>
                <input id="pprod-aliq-deson" type="text" wire:model="data.aliq_deson" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
            </div>
            <div class="erp-produtos-impostos__field">
                <label
                    for="pprod-motivo-deson"
                    class="erp-produtos-impostos__label--hint"
                    title="Motivo da Desoneração do ICMS (motDesICMS) — código da tabela SEFAZ (ex.: 1=táxi, 3=produtor agropecuário, 9=outros)."
                >Mot. Deson.</label>
                <input
                    id="pprod-motivo-deson"
                    type="text"
                    wire:model="data.motivo_desoneracao"
                    data-mask="integer"
                    class="erp-pcad-form__input erp-pcad-form__input--tax-sm"
                    title="Motivo da Desoneração do ICMS (motDesICMS) — código da tabela SEFAZ (ex.: 1=táxi, 3=produtor agropecuário, 9=outros)."
                >
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
                        <label for="pprod-iva-cst">CST IBS/CBS</label>
                        <input id="pprod-iva-cst" type="text" wire:model="data.iva_cst" maxlength="3" class="erp-pcad-form__input erp-pcad-form__input--tax-xs">
                    </div>
                    <div class="erp-produtos-impostos__field erp-produtos-impostos__field--class-trib">
                        <label for="pprod-cclass-trib">Classificação Tributária</label>
                        <div class="erp-produtos-impostos__cclass-wrap">
                            <input id="pprod-cclass-trib" type="text" wire:model="data.cclass_trib" maxlength="10" class="erp-pcad-form__input erp-pcad-form__input--tax-sm erp-produtos-impostos__cclass-input">
                            <button
                                type="button"
                                class="erp-pcad-form__btn erp-produtos-impostos__cclass-lupa"
                                wire:click="openCclassTribModal"
                                title="Consultar Classificação Tributária IVA"
                            >
                                <span class="erp-pcad-form__btn-icon">🔍</span>
                            </button>
                        </div>
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-aliq-ibs-uf">Aliq IBS UF</label>
                        <input id="pprod-aliq-ibs-uf" type="text" wire:model="data.aliq_ibs_uf" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-aliq-cbs">Aliq CBS</label>
                        <input id="pprod-aliq-cbs" type="text" wire:model="data.aliq_cbs" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-aliq-ibs-mun">Aliq IBS Mun</label>
                        <input id="pprod-aliq-ibs-mun" type="text" wire:model="data.aliq_ibs_mun" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                </div>
                <div class="erp-produtos-impostos__fields">
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-aliq-adrem-ibs">Aliq Adrem IBS</label>
                        <input id="pprod-aliq-adrem-ibs" type="text" wire:model="data.aliq_adrem_ibs" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-aliq-adrem-cbs">Aliq Adrem CBS</label>
                        <input id="pprod-aliq-adrem-cbs" type="text" wire:model="data.aliq_adrem_cbs" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-reducao-cbs">Redução CBS</label>
                        <input id="pprod-reducao-cbs" type="text" wire:model="data.reducao_cbs" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                    <div class="erp-produtos-impostos__field">
                        <label for="pprod-reducao-ibs">Redução IBS</label>
                        <input id="pprod-reducao-ibs" type="text" wire:model="data.reducao_ibs" data-mask="percent-br-4" class="erp-pcad-form__input erp-pcad-form__input--tax-sm">
                    </div>
                </div>
            </div>
        </div>
    </fieldset>
</div>
