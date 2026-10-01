<div class="erp-fv-mon-detalhe-panel">
    @include('filament.components.erp.forca-vendas.monitor-detail')
    @include('filament.components.erp.forca-vendas.monitor-action-bar', [
        'actionWirePrefix' => '$parent.',
    ])
    @include('filament.components.erp.forca-vendas.tela-venda.margem-venda', [
        'margemColValorVendido' => 'Vlr. venda',
    ])
</div>
