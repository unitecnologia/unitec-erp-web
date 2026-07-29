<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->importarXmlModalOpen): ?>
    <?php
        $item = $this->importarXmlItemIndex !== null
            ? ($this->importarXmlItens[$this->importarXmlItemIndex] ?? null)
            : null;
        $temNaoVinculado = collect($this->importarXmlItens)->contains(fn ($row) => ! ($row['vinculado'] ?? false));
    ?>
    <div
        class="erp-lookup-modal erp-nf-forn-import-xml-modal"
        wire:keydown.escape.window="requestCloseImportarXmlModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="requestCloseImportarXmlModal"></div>

        <div
            class="erp-lookup-modal__window erp-nf-forn-import-xml-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-nf-forn-import-xml-title"
        >
            <div class="erp-lookup-modal__titlebar erp-nf-forn-import-xml-modal__titlebar">
                <span id="erp-nf-forn-import-xml-title">Importar XML</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="requestCloseImportarXmlModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nf-forn-import-xml-modal__body">
                <section class="erp-nf-forn-import-xml-modal__panel erp-nf-forn-import-xml-modal__panel--topo">
                    <div class="erp-nf-forn-import-xml-modal__toolbar">
                        <div class="erp-nf-forn-import-xml-modal__tool-group">
                            <label class="erp-nf-forn-import-xml-modal__tool-btn erp-nf-forn-import-xml-modal__tool-btn--file" title="Selecionar arquivo XML">
                                <span class="erp-nf-forn-import-xml-modal__tool-icon">📁</span>
                                <span>Buscar XML</span>
                                <input
                                    type="file"
                                    accept=".xml,application/xml,text/xml"
                                    wire:model="importarXmlUpload"
                                    class="erp-nf-forn-import-xml-modal__file-input"
                                    title="Somente arquivos .xml"
                                    <?php if($this->importarXmlImportProgressOpen || $this->importarXmlCadastroProgressOpen): echo 'disabled'; endif; ?>
                                >
                            </label>
                            <button type="button" class="erp-nf-forn-import-xml-modal__tool-btn erp-nf-forn-import-xml-modal__tool-btn--ok" wire:click="finalizarImportarXml" <?php if($temNaoVinculado || $this->importarXmlImportProgressOpen || $this->importarXmlCadastroProgressOpen): echo 'disabled'; endif; ?>>
                                <span class="erp-nf-forn-import-xml-modal__tool-icon">✓</span>
                                <span>Finalizar</span>
                            </button>
                        </div>

                        <div class="erp-nf-forn-import-xml-modal__fields">
                            <div class="erp-nf-forn-import-xml-modal__fields-row">
                                <label class="erp-nf-forn-import-xml-modal__field erp-nf-forn-import-xml-modal__field--chave">
                                    <span>Chave NFe</span>
                                    <input type="text" readonly value="<?php echo e($this->importarXmlHeader['chave'] ?? ''); ?>">
                                </label>
                                <label class="erp-nf-forn-import-xml-modal__field erp-nf-forn-import-xml-modal__field--date">
                                    <span>Data Entrada</span>
                                    <input type="text" readonly value="<?php echo e($this->importarXmlHeader['data_entrada'] ?? '—'); ?>">
                                </label>
                                <label class="erp-nf-forn-import-xml-modal__field erp-nf-forn-import-xml-modal__field--date">
                                    <span>Data Emissão</span>
                                    <input type="text" readonly value="<?php echo e($this->importarXmlHeader['data_emissao'] ?? '—'); ?>">
                                </label>
                            </div>

                            <div class="erp-nf-forn-import-xml-modal__fields-row">
                                <div class="erp-nf-forn-import-xml-modal__cfop-wrap">
                                    <label class="erp-nf-forn-import-xml-modal__field erp-nf-forn-import-xml-modal__field--cfop">
                                        <span>CFOP</span>
                                        <?php echo $__env->make('filament.components.erp.notas-fornecedores.cfop-combo', [
                                            'value' => $this->importarXmlHeader['cfop'] ?? '',
                                            'itemIndex' => null,
                                            'options' => $this->importarXmlCfopOptions,
                                        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                    </label>
                                    <button
                                        type="button"
                                        class="erp-nf-forn-import-xml-modal__cfop-apply"
                                        wire:click="aplicarCfopHeaderEmTodosXml"
                                        title="Aplicar o CFOP do cabeçalho a todos os itens"
                                    >Aplicar a todos</button>
                                </div>
                                <label class="erp-nf-forn-import-xml-modal__field erp-nf-forn-import-xml-modal__field--grow">
                                    <span class="erp-nf-forn-import-xml-modal__field-label-row">
                                        <span>Fornecedor</span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($this->importarXmlHeader['fornecedor_status'] ?? '') !== ''): ?>
                                            <span
                                                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                                    'erp-nf-forn-import-xml-modal__fornecedor-badge',
                                                    'erp-nf-forn-import-xml-modal__fornecedor-badge--existente' => ($this->importarXmlHeader['fornecedor_status'] ?? '') === 'existente',
                                                    'erp-nf-forn-import-xml-modal__fornecedor-badge--automatico' => ($this->importarXmlHeader['fornecedor_status'] ?? '') === 'automatico',
                                                    'erp-nf-forn-import-xml-modal__fornecedor-badge--alerta' => ($this->importarXmlHeader['fornecedor_status'] ?? '') === 'sem_documento',
                                                ]); ?>"
                                                title="<?php echo e($this->importarXmlHeader['fornecedor_status_label'] ?? ''); ?>"
                                            ><?php echo e($this->importarXmlHeader['fornecedor_status_label'] ?? ''); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </span>
                                    <input type="text" readonly value="<?php echo e($this->importarXmlHeader['fornecedor'] ?? '—'); ?>">
                                </label>
                                <label class="erp-nf-forn-import-xml-modal__field erp-nf-forn-import-xml-modal__field--cnpj">
                                    <span>CNPJ</span>
                                    <input type="text" readonly value="<?php echo e($this->importarXmlHeader['cnpj'] ?? '—'); ?>">
                                </label>
                                <label class="erp-nf-forn-import-xml-modal__field erp-nf-forn-import-xml-modal__field--uf">
                                    <span>UF</span>
                                    <input type="text" readonly value="<?php echo e($this->importarXmlHeader['uf'] ?? '—'); ?>">
                                </label>
                                <label class="erp-nf-forn-import-xml-modal__field erp-nf-forn-import-xml-modal__field--nota">
                                    <span>Nota Fiscal</span>
                                    <input type="text" readonly value="<?php echo e($this->importarXmlHeader['numero'] ?? '—'); ?>">
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($temNaoVinculado): ?>
                    <p class="erp-nf-forn-import-xml-modal__alert">
                        ATENÇÃO — Verifique se os produtos em vermelho estão vinculados corretamente
                    </p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <section class="erp-nf-forn-import-xml-modal__panel erp-nf-forn-import-xml-modal__panel--itens">
                    <div class="erp-nf-forn-import-xml-modal__main">
                        <div class="erp-nf-forn-import-xml-modal__grid-wrap">
                            <table class="erp-nf-forn-import-xml-modal__grid">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descrição</th>
                                        <th>Grupo</th>
                                        <th>Qtd. Emb.</th>
                                        <th>Qtd. Unid.</th>
                                        <th>Qtd. Total</th>
                                        <th>Und</th>
                                        <th>Prc. Unitário</th>
                                        <th>Pr. Venda</th>
                                        <th>CFOP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->importarXmlItens; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr
                                            wire:click="selectImportarXmlItem(<?php echo e($index); ?>)"
                                            class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                                'erp-nf-forn-import-xml-modal__row',
                                                'erp-nf-forn-import-xml-modal__row--selected' => $this->importarXmlItemIndex !== null && $this->importarXmlItemIndex === $index,
                                                'erp-nf-forn-import-xml-modal__row--unlinked' => ! ($row['vinculado'] ?? false),
                                            ]); ?>"
                                        >
                                            <td>
                                                <?php
                                                    $codigoSistema = trim((string) ($row['produto_codigo'] ?? ''));
                                                    $codigoFornecedor = trim((string) ($row['codigo'] ?? ''));
                                                ?>
                                                <div
                                                    class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--codigo erp-nf-forn-import-xml-modal__cell-input--readonly"
                                                    title="<?php echo e($codigoSistema !== ''
                                                        ? 'Código do sistema'
                                                        : ($codigoFornecedor !== '' && $codigoFornecedor !== '—'
                                                            ? 'Aguardando vínculo — código do fornecedor: '.$codigoFornecedor
                                                            : 'Sem código do sistema')); ?>"
                                                ><?php echo e($codigoSistema !== '' ? $codigoSistema : '—'); ?></div>
                                            </td>
                                            <td>
                                                <?php
                                                    $descricaoSistema = trim((string) ($row['produto_descricao'] ?? ''));
                                                    $descricaoXml = trim((string) ($row['descricao'] ?? ''));
                                                    $descricaoExibir = $descricaoSistema !== '' ? $descricaoSistema : $descricaoXml;
                                                ?>
                                                <div
                                                    class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                                        'erp-nf-forn-import-xml-modal__cell-input',
                                                        'erp-nf-forn-import-xml-modal__cell-input--desc',
                                                        'erp-nf-forn-import-xml-modal__cell-input--readonly',
                                                        'erp-nf-forn-import-xml-modal__desc' => ! ($row['vinculado'] ?? false),
                                                    ]); ?>"
                                                    title="<?php echo e($descricaoSistema !== '' && $descricaoXml !== '' && $descricaoSistema !== $descricaoXml
                                                        ? 'Sistema: '.$descricaoSistema.' | XML: '.$descricaoXml
                                                        : $descricaoExibir); ?>"
                                                ><?php echo e($descricaoExibir !== '' ? $descricaoExibir : '—'); ?></div>
                                            </td>
                                            <td>
                                                <select
                                                    wire:model.blur="importarXmlItens.<?php echo e($index); ?>.grupo"
                                                    wire:click.stop
                                                    class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-select"
                                                >
                                                    <option value="">—</option>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($row['grupo'] ?? '') && ! array_key_exists($row['grupo'], $this->importarXmlGruposOptions)): ?>
                                                        <option value="<?php echo e($row['grupo']); ?>"><?php echo e($row['grupo']); ?></option>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->importarXmlGruposOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $grupoNome): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <option value="<?php echo e($grupoNome); ?>"><?php echo e($grupoNome); ?></option>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    wire:model.blur="importarXmlItens.<?php echo e($index); ?>.qtd_emb"
                                                    wire:blur="recalcularLinhaItemXml(<?php echo e($index); ?>)"
                                                    wire:click.stop
                                                    class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--num"
                                                    autocomplete="off"
                                                    title="Quantidade do XML (qCom)"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    wire:model.blur="importarXmlItens.<?php echo e($index); ?>.qtd_unid"
                                                    wire:blur="recalcularLinhaItemXml(<?php echo e($index); ?>)"
                                                    wire:click.stop
                                                    class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--num"
                                                    autocomplete="off"
                                                    title="Fator: unidades de estoque por embalagem"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    wire:model.blur="importarXmlItens.<?php echo e($index); ?>.qtd_total"
                                                    wire:click.stop
                                                    class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--num"
                                                    autocomplete="off"
                                                    title="Total estoque = Qtd. Emb. × Qtd. Unid."
                                                    readonly
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    wire:model.blur="importarXmlItens.<?php echo e($index); ?>.und"
                                                    wire:click.stop
                                                    class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--center"
                                                    autocomplete="off"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    wire:model.blur="importarXmlItens.<?php echo e($index); ?>.prc_unitario"
                                                    wire:blur="recalcularLinhaItemXml(<?php echo e($index); ?>)"
                                                    wire:click.stop
                                                    class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--num"
                                                    autocomplete="off"
                                                    title="Preço unitário da nota (por embalagem)"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    wire:model.blur="importarXmlItens.<?php echo e($index); ?>.pr_venda"
                                                    wire:click.stop
                                                    class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--num"
                                                    autocomplete="off"
                                                >
                                            </td>
                                            <td>
                                                <?php echo $__env->make('filament.components.erp.notas-fornecedores.cfop-combo', [
                                                    'value' => $row['cfop'] ?? '',
                                                    'itemIndex' => $index,
                                                    'options' => $this->importarXmlCfopOptions,
                                                    'compact' => true,
                                                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="10">Nenhum item no XML.</td>
                                        </tr>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <aside class="erp-nf-forn-import-xml-modal__side">
                            <button
                                type="button"
                                wire:click="cadastrarProdutoXmlSelecionado"
                                title="Cadastrar produto selecionado a partir do XML"
                            >Cadastrar</button>
                            <button
                                type="button"
                                wire:click="cadastrarTodosProdutosXml"
                                <?php if($this->importarXmlCadastroProgressOpen): echo 'disabled'; endif; ?>
                                title="Cadastra automaticamente um a um; avisa se já existir"
                            >Cadastrar Todos</button>
                            <button
                                type="button"
                                wire:click="openPesquisarProdutoXml"
                                title="Pesquisar produto cadastrado para vincular ao item"
                            >Pesquisar</button>
                            <button
                                type="button"
                                wire:click="desvincularProdutoXmlSelecionado"
                                title="Desvincular produto do item selecionado"
                            >Desvincular</button>
                            <button
                                type="button"
                                wire:click="desvincularTodosProdutosXml"
                                title="Desvincular todos os itens"
                            >Desv. Todos</button>
                            <button
                                type="button"
                                wire:click="aplicarGrupoXmlSelecionado"
                                title="Aplicar o grupo do item selecionado a todos os itens"
                            >Grupo</button>
                            <button
                                type="button"
                                wire:click="aplicarCfopXmlSelecionado"
                                title="Aplicar o CFOP do item selecionado a todos os itens"
                            >CFOP</button>
                        </aside>
                    </div>
                </section>

                <section class="erp-nf-forn-import-xml-modal__panel erp-nf-forn-import-xml-modal__panel--vinculo">
                    <span class="erp-nf-forn-import-xml-modal__vinculo-label">Produto Vinculado</span>
                    <div class="erp-nf-forn-import-xml-modal__vinculo-box">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item && ($item['vinculado'] ?? false)): ?>
                            <strong><?php echo e($item['produto_codigo'] ?? '—'); ?></strong>
                            — <?php echo e($item['produto_descricao'] ?? $item['descricao']); ?>

                        <?php else: ?>
                            <em>Nenhum produto vinculado — cadastre ou pesquise para vincular ao fornecedor.</em>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </section>

                <section class="erp-nf-forn-import-xml-modal__panel erp-nf-forn-import-xml-modal__panel--rodape">
                    <div class="erp-nf-forn-import-xml-modal__tabs">
                        <button
                            type="button"
                            wire:click="setImportarXmlTab('detalhes')"
                            class="<?php echo \Illuminate\Support\Arr::toCssClasses(['erp-nf-forn-import-xml-modal__tab', 'erp-nf-forn-import-xml-modal__tab--active' => $this->importarXmlTab === 'detalhes']); ?>"
                        >Detalhes Itens - Fornecedor</button>
                        <button
                            type="button"
                            wire:click="setImportarXmlTab('totais')"
                            class="<?php echo \Illuminate\Support\Arr::toCssClasses(['erp-nf-forn-import-xml-modal__tab', 'erp-nf-forn-import-xml-modal__tab--active' => $this->importarXmlTab === 'totais']); ?>"
                        >Totais</button>
                    </div>

                    <div class="erp-nf-forn-import-xml-modal__tab-body">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->importarXmlTab === 'totais'): ?>
                            <?php
                                $totaisCampos = [
                                    ['key' => 'subtotal', 'label' => 'SubTotal'],
                                    ['key' => 'frete', 'label' => 'Frete'],
                                    ['key' => 'despesas', 'label' => 'Despesas'],
                                    ['key' => 'seguro', 'label' => 'Seguro'],
                                    ['key' => 'desconto', 'label' => 'Desconto'],
                                    ['key' => 'total', 'label' => 'Total'],
                                    ['key' => 'base_icms', 'label' => 'Base ICMS'],
                                    ['key' => 'total_icms', 'label' => 'Total ICMS'],
                                    ['key' => 'base_pis', 'label' => 'Base PIS'],
                                    ['key' => 'total_pis', 'label' => 'Total PIS'],
                                    ['key' => 'base_cofins', 'label' => 'Base Cofins'],
                                    ['key' => 'total_cofins', 'label' => 'Total Cofins'],
                                    ['key' => 'base_ipi', 'label' => 'Base IPI'],
                                    ['key' => 'total_ipi', 'label' => 'Total IPI'],
                                    ['key' => 'base_st', 'label' => 'Base ST'],
                                    ['key' => 'total_st', 'label' => 'Total ST'],
                                ];
                            ?>
                            <div class="erp-nf-forn-import-xml-modal__totais">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $totaisCampos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <label class="erp-nf-forn-import-xml-modal__total-field">
                                        <span><?php echo e($campo['label']); ?></span>
                                        <input type="text" readonly value="<?php echo e($this->importarXmlTotais[$campo['key']] ?? '0,00'); ?>">
                                    </label>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php elseif($item): ?>
                            <?php
                                $detCodigoXml = trim((string) ($item['codigo'] ?? ''));
                                $detDescXml = trim((string) ($item['descricao'] ?? ''));
                                $detCfopXml = trim((string) ($item['cfop_xml'] ?? ''));
                                $detCfopEntrada = trim((string) ($item['cfop'] ?? ''));
                                $detQtdEmb = (string) ($item['qtd_emb'] ?? '—');
                                $detQtdTotal = (string) ($item['qtd_total'] ?? $detQtdEmb);
                                $detTotal = (string) ($item['valor_total'] ?? '0,00');
                            ?>
                            <div class="erp-nf-forn-import-xml-modal__detalhe-wrap">
                                <table class="erp-nf-forn-import-xml-modal__detalhe-grid">
                                    <thead>
                                        <tr>
                                            <th>Cód. XML</th>
                                            <th>Descrição XML</th>
                                            <th>NCM</th>
                                            <th>CFOP XML</th>
                                            <th>CFOP Entrada</th>
                                            <th>CST</th>
                                            <th>Qtd Emb</th>
                                            <th>Qtd Estoque</th>
                                            <th>Und</th>
                                            <th>Prc. Unitário</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly"><?php echo e($detCodigoXml !== '' ? $detCodigoXml : '—'); ?></div>
                                            </td>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly erp-nf-forn-import-xml-modal__cell-input--desc" title="<?php echo e($detDescXml); ?>"><?php echo e($detDescXml !== '' ? $detDescXml : '—'); ?></div>
                                            </td>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly erp-nf-forn-import-xml-modal__cell-input--center"><?php echo e(($item['ncm'] ?? '') !== '' ? $item['ncm'] : '—'); ?></div>
                                            </td>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly erp-nf-forn-import-xml-modal__cell-input--center"><?php echo e($detCfopXml !== '' ? $detCfopXml : '—'); ?></div>
                                            </td>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly erp-nf-forn-import-xml-modal__cell-input--center"><?php echo e($detCfopEntrada !== '' ? $detCfopEntrada : '—'); ?></div>
                                            </td>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly erp-nf-forn-import-xml-modal__cell-input--center"><?php echo e(($item['cst'] ?? '') !== '' ? $item['cst'] : '—'); ?></div>
                                            </td>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly erp-nf-forn-import-xml-modal__cell-input--num"><?php echo e($detQtdEmb); ?></div>
                                            </td>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly erp-nf-forn-import-xml-modal__cell-input--num"><?php echo e($detQtdTotal); ?></div>
                                            </td>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly erp-nf-forn-import-xml-modal__cell-input--center"><?php echo e($item['und'] ?? '—'); ?></div>
                                            </td>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly erp-nf-forn-import-xml-modal__cell-input--num"><?php echo e($item['prc_unitario'] ?? '0,000'); ?></div>
                                            </td>
                                            <td>
                                                <div class="erp-nf-forn-import-xml-modal__cell-input erp-nf-forn-import-xml-modal__cell-input--readonly erp-nf-forn-import-xml-modal__cell-input--num"><?php echo e($detTotal); ?></div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="erp-nf-forn-import-xml-modal__detalhe-empty">Selecione um item na grade acima para ver os detalhes.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->importarXmlFecharConfirmOpen): ?>
        <div
            class="erp-nf-forn-import-xml-aviso is-visible erp-nf-forn-import-xml-aviso--warning"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="erp-nf-forn-import-xml-fechar-title"
            wire:keydown.escape.window="cancelCloseImportarXmlModal"
        >
            <div class="erp-nf-forn-import-xml-aviso__backdrop" wire:click="cancelCloseImportarXmlModal"></div>
            <div class="erp-nf-forn-import-xml-aviso__panel">
                <div class="erp-nf-forn-import-xml-aviso__icon" aria-hidden="true">!</div>
                <h2 id="erp-nf-forn-import-xml-fechar-title" class="erp-nf-forn-import-xml-aviso__title">
                    Fechar sem finalizar?
                </h2>
                <div class="erp-nf-forn-import-xml-aviso__text">
                    Há itens carregados nesta tela. Ao fechar, as edições desta sessão serão perdidas
                    (vínculos já gravados no cadastro permanecem).
                </div>
                <div class="erp-nf-forn-import-xml-aviso__actions">
                    <button type="button" class="erp-nf-forn-import-xml-aviso__btn erp-nf-forn-import-xml-aviso__btn--ghost" wire:click="cancelCloseImportarXmlModal">
                        Continuar editando
                    </button>
                    <button type="button" class="erp-nf-forn-import-xml-aviso__btn" wire:click="closeImportarXmlModal">
                        Fechar mesmo assim
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->importarXmlPesquisarOpen): ?>
        <div class="erp-lookup-modal erp-nf-forn-import-xml-pesquisar" wire:keydown.escape.window="closePesquisarProdutoXml">
            <div class="erp-lookup-modal__backdrop" wire:click="closePesquisarProdutoXml"></div>
            <div class="erp-lookup-modal__window erp-nf-forn-import-xml-pesquisar__window" role="dialog" aria-modal="true">
                <div class="erp-lookup-modal__titlebar">
                    <span>Pesquisar produto</span>
                    <button type="button" class="erp-lookup-modal__close" wire:click="closePesquisarProdutoXml" title="Fechar">✕</button>
                </div>
                <div class="erp-lookup-modal__body erp-nf-forn-import-xml-pesquisar__body">
                    <label class="erp-nf-forn-import-xml-pesquisar__search">
                        <span>Buscar por código, descrição, referência ou barras</span>
                        <input
                            type="text"
                            wire:model.live.debounce.200ms="importarXmlPesquisarTermo"
                            wire:keydown.enter.prevent="confirmPesquisarProdutoXml"
                            autocomplete="off"
                            autofocus
                        >
                    </label>
                    <div class="erp-nf-forn-import-xml-pesquisar__grid-wrap">
                        <table class="erp-nf-forn-import-xml-pesquisar__grid">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Descrição</th>
                                    <th>Referência</th>
                                    <th>Grupo</th>
                                    <th>Pr. Venda</th>
                                    <th>Estoque</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->importarXmlPesquisarResultados; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pIndex => $produto): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr
                                        wire:click="selectPesquisarProdutoXml(<?php echo e($pIndex); ?>)"
                                        wire:dblclick="confirmPesquisarProdutoXml"
                                        class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                            'erp-nf-forn-import-xml-pesquisar__row--selected' => $this->importarXmlPesquisarIndex === $pIndex,
                                        ]); ?>"
                                    >
                                        <td><?php echo e($produto['codigo']); ?></td>
                                        <td><?php echo e($produto['descricao']); ?></td>
                                        <td><?php echo e($produto['referencia']); ?></td>
                                        <td><?php echo e($produto['grupo'] !== '' ? $produto['grupo'] : '—'); ?></td>
                                        <td class="erp-nf-forn-import-xml-modal__num"><?php echo e($produto['preco_venda']); ?></td>
                                        <td class="erp-nf-forn-import-xml-modal__num"><?php echo e($produto['estoque']); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="6">Digite ao menos 2 caracteres para pesquisar.</td>
                                    </tr>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="erp-nf-forn-import-xml-pesquisar__actions">
                        <button type="button" wire:click="confirmPesquisarProdutoXml">Vincular</button>
                        <button type="button" wire:click="closePesquisarProdutoXml">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php
        $importProgressOpen = $this->importarXmlImportProgressOpen || $this->importarXmlCadastroProgressOpen;
        if ($this->importarXmlImportProgressOpen) {
            $progressLabel = $this->importarXmlImportProgressLabel;
            $progressDetail = $this->importarXmlImportProgressDetail;
            $progressPercent = $this->importarXmlImportProgressPercent;
            $progressMeta = $this->importarXmlImportProgressPercent.'%';
        } else {
            $progressLabel = $this->importarXmlCadastroProgressLabel;
            $progressDetail = $this->importarXmlCadastroProgressDetail;
            $progressPercent = $this->importarXmlCadastroProgressPercent;
            $progressMeta = $this->importarXmlCadastroProgressCurrent.' / '.$this->importarXmlCadastroProgressTotal
                .' — '.$this->importarXmlCadastroProgressPercent.'%';
        }
    ?>

    <div
        wire:loading.flex
        wire:target="importarXmlUpload"
        class="erp-nf-forn-import-xml-progress"
        aria-live="polite"
        aria-busy="true"
        role="status"
    >
        <div class="erp-nf-forn-import-xml-progress__backdrop" aria-hidden="true"></div>
        <div class="erp-nf-forn-import-xml-progress__panel">
            <div class="erp-nf-forn-import-xml-progress__spinner" aria-hidden="true"></div>
            <p class="erp-nf-forn-import-xml-progress__status">Enviando arquivo XML…</p>
            <p class="erp-nf-forn-import-xml-progress__detail">Aguarde o upload do arquivo</p>
            <div class="erp-nf-forn-import-xml-progress__track" aria-hidden="true">
                <div class="erp-nf-forn-import-xml-progress__bar erp-nf-forn-import-xml-progress__bar--indeterminate"></div>
            </div>
            <p class="erp-nf-forn-import-xml-progress__hint">Aguarde, não feche esta tela.</p>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($importProgressOpen): ?>
        <div
            class="erp-nf-forn-import-xml-progress is-visible"
            aria-live="polite"
            aria-busy="true"
            role="status"
        >
            <div class="erp-nf-forn-import-xml-progress__backdrop" aria-hidden="true"></div>
            <div class="erp-nf-forn-import-xml-progress__panel">
                <div class="erp-nf-forn-import-xml-progress__spinner" aria-hidden="true"></div>
                <p class="erp-nf-forn-import-xml-progress__status">
                    <?php echo e($progressLabel); ?>

                </p>
                <p class="erp-nf-forn-import-xml-progress__detail" title="<?php echo e($progressDetail); ?>">
                    <?php echo e($progressDetail); ?>

                </p>
                <div class="erp-nf-forn-import-xml-progress__track" aria-hidden="true">
                    <div
                        class="erp-nf-forn-import-xml-progress__bar"
                        style="width: <?php echo e(max(4, min(100, $progressPercent))); ?>%"
                    ></div>
                </div>
                <p class="erp-nf-forn-import-xml-progress__meta">
                    <?php echo e($progressMeta); ?>

                </p>
                <p class="erp-nf-forn-import-xml-progress__hint">Aguarde, não feche esta tela.</p>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->importarXmlAvisoOpen): ?>
        <div
            class="erp-nf-forn-import-xml-aviso is-visible erp-nf-forn-import-xml-aviso--<?php echo e($this->importarXmlAvisoTone); ?>"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="erp-nf-forn-import-xml-aviso-title"
            wire:keydown.escape.window="closeImportarXmlAviso"
        >
            <div class="erp-nf-forn-import-xml-aviso__backdrop" wire:click="closeImportarXmlAviso"></div>
            <div class="erp-nf-forn-import-xml-aviso__panel">
                <div class="erp-nf-forn-import-xml-aviso__icon" aria-hidden="true">i</div>
                <h2 id="erp-nf-forn-import-xml-aviso-title" class="erp-nf-forn-import-xml-aviso__title">
                    <?php echo e($this->importarXmlAvisoTitulo); ?>

                </h2>
                <div class="erp-nf-forn-import-xml-aviso__text">
                    <?php echo nl2br(e($this->importarXmlAvisoMensagem)); ?>

                </div>
                <button
                    type="button"
                    class="erp-nf-forn-import-xml-aviso__btn"
                    wire:click="closeImportarXmlAviso"
                >Entendido</button>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/notas-fornecedores/importar-xml-modal.blade.php ENDPATH**/ ?>