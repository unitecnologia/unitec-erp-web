<div class="erp-produtos-nutricional">
    <fieldset class="erp-produtos-tab-group">
        <legend class="erp-produtos-tab-group__legend">Porção / medida caseira</legend>
        <div class="erp-produtos-tab-group__fields">
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-porcao">Qtd. porção</label>
                <input id="pprod-nutri-porcao" type="text" wire:model="data.nutri_porcao_qtd" data-mask="integer" maxlength="3" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-unidade">Unidade</label>
                <select id="pprod-nutri-unidade" wire:model="data.nutri_porcao_unidade" class="erp-pcad-form__select">
                    <option value="0">Gramas (g)</option>
                    <option value="1">Mililitros (ml)</option>
                    <option value="2">Unidades (un)</option>
                </select>
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-medida-int">Medida (inteiro)</label>
                <input id="pprod-nutri-medida-int" type="text" wire:model="data.nutri_medida_inteiro" data-mask="integer" maxlength="2" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-fracao">Fração</label>
                <select id="pprod-nutri-fracao" wire:model="data.nutri_medida_fracao" class="erp-pcad-form__select">
                    <option value="0">0</option>
                    <option value="1">1/4</option>
                    <option value="2">1/3</option>
                    <option value="3">1/2</option>
                    <option value="4">2/3</option>
                    <option value="5">3/4</option>
                </select>
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-medida">Medida caseira</label>
                <select id="pprod-nutri-medida" wire:model="data.nutri_medida_tipo" class="erp-pcad-form__select">
                    <option value="00">Colher(es) de sopa</option>
                    <option value="01">Colher(es) de café</option>
                    <option value="02">Colher(es) de chá</option>
                    <option value="03">Xícara(s)</option>
                    <option value="05">Unidade(s)</option>
                    <option value="06">Pacote(s)</option>
                    <option value="07">Fatia(s)</option>
                    <option value="09">Pedaço(s)</option>
                    <option value="15">Copo(s)</option>
                    <option value="16">Porção(ões)</option>
                    <option value="20">Bife(s)</option>
                    <option value="21">Filé(s)</option>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset class="erp-produtos-tab-group">
        <legend class="erp-produtos-tab-group__legend">Tabela nutricional (por porção)</legend>
        <div class="erp-produtos-tab-group__fields">
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-kcal">Valor energético (kcal)</label>
                <input id="pprod-nutri-kcal" type="text" wire:model="data.nutri_valor_energetico" class="erp-pcad-form__input" placeholder="0,0">
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-carb">Carboidratos (g)</label>
                <input id="pprod-nutri-carb" type="text" wire:model="data.nutri_carboidratos" class="erp-pcad-form__input" placeholder="0,0">
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-prot">Proteínas (g)</label>
                <input id="pprod-nutri-prot" type="text" wire:model="data.nutri_proteinas" class="erp-pcad-form__input" placeholder="0,0">
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-gord">Gorduras totais (g)</label>
                <input id="pprod-nutri-gord" type="text" wire:model="data.nutri_gorduras_totais" class="erp-pcad-form__input" placeholder="0,0">
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-sat">Gorduras saturadas (g)</label>
                <input id="pprod-nutri-sat" type="text" wire:model="data.nutri_gorduras_saturadas" class="erp-pcad-form__input" placeholder="0,0">
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-trans">Gorduras trans (g)</label>
                <input id="pprod-nutri-trans" type="text" wire:model="data.nutri_gorduras_trans" class="erp-pcad-form__input" placeholder="0,0">
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-fibra">Fibra alimentar (g)</label>
                <input id="pprod-nutri-fibra" type="text" wire:model="data.nutri_fibra" class="erp-pcad-form__input" placeholder="0,0">
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-nutri-sodio">Sódio (mg)</label>
                <input id="pprod-nutri-sodio" type="text" wire:model="data.nutri_sodio" class="erp-pcad-form__input" placeholder="0,0">
            </div>
        </div>
        <p class="erp-produtos-tab-group__hint">Usado na etiqueta da balança Toledo MGV (arquivo INFNUTRI). Valores por porção informada.</p>
    </fieldset>
</div>
