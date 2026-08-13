@if ($this->confirmarModalAberto)
    @php
        $pesoInfo = $this->pesoConfirmacaoAtual();
        $pedidoLabel = $this->labelEntregaConfirmacaoAtual();
        $progresso = $this->confirmacaoProgressoLabel();
        $vaiEntrega = $this->confirmarVaiParaEntrega;
    @endphp
    <div
        class="erp-expedicao-confirmar"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-expedicao-confirmar-title"
        wire:keydown.escape="fecharConfirmarModal"
    >
        <div class="erp-expedicao-confirmar__box">
            <div class="erp-expedicao-confirmar__header">
                <h2 class="erp-expedicao-confirmar__title" id="erp-expedicao-confirmar-title">Confirmar expedição</h2>
                <span class="erp-expedicao-confirmar__progress">{{ $progresso }}</span>
            </div>

            <p class="erp-expedicao-confirmar__pedido">{{ $pedidoLabel }}</p>

            <div class="erp-expedicao-confirmar__pergunta">
                <span class="erp-expedicao-confirmar__pergunta-label">O pedido vai para entrega?</span>
                <div class="erp-expedicao-confirmar__opcoes">
                    <button
                        type="button"
                        wire:click="escolherConfirmarTipoSaida(true)"
                        class="erp-expedicao-confirmar__opcao {{ $vaiEntrega === true ? 'is-active' : '' }}"
                    >Sim — entrega</button>
                    <button
                        type="button"
                        wire:click="escolherConfirmarTipoSaida(false)"
                        class="erp-expedicao-confirmar__opcao {{ $vaiEntrega === false ? 'is-active' : '' }}"
                    >Não — cliente retirou</button>
                </div>
            </div>

            @if ($vaiEntrega === true)
                <div class="erp-expedicao-confirmar__secao">
                    <label class="erp-expedicao-confirmar__field">
                        <span class="erp-expedicao-confirmar__field-label">Transportadora</span>
                        <button
                            type="button"
                            wire:click="modulePendingTransportadora"
                            class="erp-expedicao-confirmar__transportadora-btn"
                        >Selecionar transportadora…</button>
                        <span class="erp-expedicao-confirmar__field-hint">Cadastro em implementação.</span>
                    </label>

                    <label class="erp-expedicao-confirmar__field">
                        <span class="erp-expedicao-confirmar__field-label">Volumes</span>
                        <input
                            type="text"
                            inputmode="numeric"
                            class="erp-expedicao-confirmar__input"
                            wire:model.live="confirmarVolumes"
                            autocomplete="off"
                        >
                    </label>

                    <div class="erp-expedicao-confirmar__peso">
                        <span class="erp-expedicao-confirmar__peso-label">Peso calculado</span>
                        <strong class="erp-expedicao-confirmar__peso-valor">{{ $pesoInfo['peso_formatado'] }} kg</strong>
                    </div>

                    <p class="erp-expedicao-confirmar__aviso erp-expedicao-confirmar__aviso--info">
                        Confira o peso na balança antes de despachar.
                    </p>

                    @if ($pesoInfo['itens_sem_peso'] > 0)
                        <p class="erp-expedicao-confirmar__aviso erp-expedicao-confirmar__aviso--warn">
                            {{ $pesoInfo['itens_sem_peso'] }} item(ns) sem peso cadastrado — confira manualmente.
                        </p>
                    @endif
                </div>
            @elseif ($vaiEntrega === false)
                <div class="erp-expedicao-confirmar__secao">
                    <p class="erp-expedicao-confirmar__texto-retirada">
                        Retirada pelo cliente. Você pode imprimir o romaneio de retirada para assinatura no papel.
                    </p>
                    <button
                        type="button"
                        wire:click="imprimirRomaneioRetiradaAtual"
                        class="erp-expedicao-confirmar__btn-sec"
                    >Imprimir romaneio de retirada</button>
                </div>
            @endif

            <div class="erp-expedicao-confirmar__acoes">
                <button
                    type="button"
                    wire:click="fecharConfirmarModal"
                    class="erp-expedicao-confirmar__btn erp-expedicao-confirmar__btn--cancel"
                >Cancelar</button>
                <button
                    type="button"
                    wire:click="confirmarPedidoAtual"
                    class="erp-expedicao-confirmar__btn erp-expedicao-confirmar__btn--ok"
                >Confirmar pedido</button>
            </div>
        </div>
    </div>
@endif
