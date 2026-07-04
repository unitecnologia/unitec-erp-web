@if ($this->produtoNaoEncontradoCodigo !== null)
    <div class="erp-pdv-naoencontrado" role="alert" aria-live="assertive">
        <div class="erp-pdv-naoencontrado__box">
            <div class="erp-pdv-naoencontrado__icon" aria-hidden="true">!</div>
            <h2 class="erp-pdv-naoencontrado__title">PRODUTO NÃO ENCONTRADO</h2>
            <p class="erp-pdv-naoencontrado__codigo">Código: <strong>{{ $this->produtoNaoEncontradoCodigo }}</strong></p>
            @if ($this->produtoNaoEncontradoCount > 1)
                <p class="erp-pdv-naoencontrado__count">{{ $this->produtoNaoEncontradoCount }} leituras sem cadastro</p>
            @endif
            <button
                type="button"
                wire:click="fecharProdutoNaoEncontrado"
                class="erp-pdv-naoencontrado__btn"
                tabindex="-1"
            >OK</button>
            <p class="erp-pdv-naoencontrado__hint">Você pode continuar passando os próximos produtos. Este aviso permanece até clicar em OK.</p>
        </div>
    </div>
@endif
