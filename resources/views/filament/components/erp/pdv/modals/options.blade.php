@if ($this->activeModal === 'options')

    <div class="erp-pdv-modal erp-pdv-modal--menu" role="dialog" aria-label="Opções do PDV">

        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>

        <div class="erp-pdv-options" id="erp-pdv-options-panel">

            <ul class="erp-pdv-options__list">

                <li><button type="button" wire:click="toggleCaixa"><u>F2</u> - Abrir/Fechar Caixa</button></li>

                @if ($this->pdvExibirF3Vendedor)
                    <li><button type="button" wire:click="openVendedorModal"><u>F3</u> - Vendedor</button></li>
                @endif

                @if ($this->pdvExibirF4BuscaAvancada)
                    <li><button type="button" wire:click="openBuscaAvancadaModal"><u>F4</u> - Busca Avançada</button></li>
                @endif

                <li><button type="button" wire:click="openRemoverItensModal"><u>F11</u> - Remover Itens</button></li>

                <li><button type="button" wire:click="deletarItemCupom"><u>DEL</u> - Deleta Item</button></li>

                @if ($this->pdvPermitirDescontoItem)
                    <li><button type="button" wire:click="openDescontoItemModal"><u>Ctrl+D</u> - Desconto / Acréscimo</button></li>
                @endif

                @if ($this->pdvHabilitarTabelaPreco)
                    <li><button type="button" wire:click="openTabelaPrecoModal">Tabela de Preço</button></li>
                @endif

                <li><button type="button" wire:click="abrirGaveta"><u>Ctrl+A</u> - Abrir Gaveta</button></li>

                <li><button type="button" wire:click="openReceberModal"><u>Ctrl+R</u> - Receber</button></li>

                <li><button type="button" wire:click="openBuscaPrecoModal"><u>Ctrl+L</u> - Busca Preço</button></li>

                @if ($this->pdvUsaTef)
                    <li><button type="button" wire:click="moduleStubTef"><u>Ctrl+T</u> - Administrativo TEF</button></li>
                @endif

                <li><button type="button" wire:click="moduleStubNfce"><u>Ctrl+I</u> - Reimprimir NFCe</button></li>

                <li><button type="button" wire:click="openConsultaVendaModal"><u>Ctrl+O</u> - Consulta / Estorno Venda</button></li>

                <li><button type="button" wire:click="openReimprimirModal"><u>Ctrl+P</u> - Reimprimir Pedido</button></li>

            </ul>



            @if ($this->pdvExibeMesas)

                <p class="erp-pdv-options__section-title">Atalhos p/ Módulo Mesas (stub web)</p>

                <ul class="erp-pdv-options__list erp-pdv-options__list--secondary">

                    <li><button type="button" wire:click="moduleStubMesa('Imprimir Pedido')"><u>Ctrl+S</u> - Imprimir Pedido</button></li>

                    <li><button type="button" wire:click="moduleStubMesa('Abrir Mesa')"><u>Ctrl+N</u> - Abrir Mesa</button></li>

                    <li><button type="button" wire:click="moduleStubMesa('Imprimir Item')"><u>Ctrl+E</u> - Imprimir Item</button></li>

                    <li><button type="button" wire:click="moduleStubMesa('Transferir Mesa')"><u>Ctrl+B</u> - Transferir Mesa</button></li>

                    <li><button type="button" wire:click="moduleStubMesa('Atualiza Mesas')"><u>Ctrl+M</u> - Atualiza Mesas</button></li>

                </ul>

            @endif

        </div>

    </div>

@endif

