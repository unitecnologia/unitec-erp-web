<div class="erp-etiquetas-novo" wire:ignore.self>
    <div class="erp-etiquetas-novo__panel">
        <span class="erp-etiquetas-novo__title">Parametros</span>

        <label class="erp-etiquetas-novo__field">
            Modelos de Etiquetas
            <select class="erp-etiquetas-novo__select" disabled>
                <option>Modelo padrão</option>
            </select>
        </label>

        <label class="erp-etiquetas-novo__field">
            Impressoras
            <select class="erp-etiquetas-novo__select" disabled>
                <option>Impressora padrão</option>
            </select>
        </label>

        <label class="erp-etiquetas-novo__field">
            [F4] - Qtde Etiquetas
            <input type="number" min="1" wire:model="qtdEtiquetas" class="erp-etiquetas-novo__input">
        </label>

        <fieldset class="erp-etiquetas-novo__radio-group">
            <legend>[F2] - Tipo de Busca</legend>
            <label><input type="radio" wire:model.live="tipoBusca" value="codigo_barras"> Código de Barras</label>
            <label><input type="radio" wire:model.live="tipoBusca" value="codigo"> Código</label>
            <label><input type="radio" wire:model.live="tipoBusca" value="descricao"> Descrição</label>
        </fieldset>

        <input type="text" wire:model="termoBusca" class="erp-etiquetas-novo__input erp-etiquetas-novo__input--search" placeholder="Digite aqui para pesquisar">

        <div class="erp-etiquetas-novo__actions-top">
            <button type="button" wire:click="limpar" class="erp-etiquetas-novo__btn">Limpar</button>
            <button type="button" wire:click="imprimir" class="erp-etiquetas-novo__btn erp-etiquetas-novo__btn--primary">Imprimir</button>
        </div>
    </div>

    <div class="erp-etiquetas-novo__tables">
        <div class="erp-etiquetas-novo__table-block">
            <span class="erp-etiquetas-novo__subtitle">[F3] - Pesquisar</span>
            <div class="erp-etiquetas-novo__table-placeholder">Resultados da pesquisa (Fase 2)</div>
        </div>
        <div class="erp-etiquetas-novo__table-block">
            <span class="erp-etiquetas-novo__subtitle">Produtos a Serem Impressos</span>
            <div class="erp-etiquetas-novo__table-placeholder">Nenhum produto selecionado</div>
        </div>
    </div>

    <div class="erp-etiquetas-novo__footer">
        <button type="button" wire:click="closeScreen" class="erp-etiquetas-novo__exit">Sair [ ESC ]</button>
    </div>
</div>
