@php
    $precoVarejo = $this->data['preco_venda'] ?? '0,00';
    $precoAtacado = $this->data['preco_atacado'] ?? '0,00';
    $precoEspecial = $this->data['preco_especial'] ?? '0,00';
@endphp

<div class="erp-produtos-child-grid">
    <div class="erp-produtos-tabpreco__form">
        <p class="erp-produtos-tabpreco__hint">
            Marque quais preços da Precificação este produto pode usar na venda.
            Com <strong>Usar Tab. Preço</strong> ativo, a venda exige escolher entre as tabelas marcadas.
            Desmarcar a flag só desativa o uso — as tabelas escolhidas permanecem salvas.
        </p>
        <div class="erp-produtos-tabpreco__checks">
            <label class="erp-produtos-tabpreco__check">
                <input type="checkbox" wire:model.live="tabPrecoVarejo">
                <span class="erp-produtos-tabpreco__check-body">
                    <span class="erp-produtos-tabpreco__check-title">Varejo</span>
                    <span class="erp-produtos-tabpreco__check-price">R$ {{ $precoVarejo }}</span>
                </span>
            </label>
            <label class="erp-produtos-tabpreco__check">
                <input type="checkbox" wire:model.live="tabPrecoAtacado">
                <span class="erp-produtos-tabpreco__check-body">
                    <span class="erp-produtos-tabpreco__check-title">Atacado</span>
                    <span class="erp-produtos-tabpreco__check-price">R$ {{ $precoAtacado }}</span>
                </span>
            </label>
            <label class="erp-produtos-tabpreco__check">
                <input type="checkbox" wire:model.live="tabPrecoEspecial">
                <span class="erp-produtos-tabpreco__check-body">
                    <span class="erp-produtos-tabpreco__check-title">Especial</span>
                    <span class="erp-produtos-tabpreco__check-price">R$ {{ $precoEspecial }}</span>
                </span>
            </label>
        </div>
    </div>
</div>
