<p class="erp-nfe-lancamento-modal__grid-hint">

    Código + <strong>Enter</strong> busca o produto e vai para descrição. Digite na descrição para pesquisar (como PDV).

    <strong>Enter</strong> confirma cada campo até incluir o item. <strong>Ctrl + Delete</strong> exclui linha selecionada.

</p>



<div class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap--itens">

    <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid erp-nfe-lancamento-modal__grid--itens">

        <colgroup>

            <col class="erp-nfe-col-item">

            <col class="erp-nfe-col-codigo">

            <col class="erp-nfe-col-produto">

            <col class="erp-nfe-col-cfop">

            <col class="erp-nfe-col-cst">

            <col class="erp-nfe-col-preco">

            <col class="erp-nfe-col-qtd">

            <col class="erp-nfe-col-unid">

            <col class="erp-nfe-col-total">

            <col class="erp-nfe-col-pedido">

        </colgroup>

        <thead>

            <tr>

                <th>Item</th>

                <th>Cód.</th>

                <th>Produto</th>

                <th class="erp-nfe-lancamento-modal__center">CFOP</th>

                <th class="erp-nfe-lancamento-modal__center">CST</th>

                <th class="erp-nfe-lancamento-modal__num">Preço</th>

                <th class="erp-nfe-lancamento-modal__num">Qtd.</th>

                <th class="erp-nfe-lancamento-modal__center">Unid.</th>

                <th class="erp-nfe-lancamento-modal__num">Total</th>

                <th class="erp-nfe-lancamento-modal__center">Pedido</th>

            </tr>

        </thead>

        <tbody>

            <tr @class([

                'erp-nfe-lancamento-modal__grid-entry',

                'erp-nfe-lancamento-modal__grid-entry--pending' => $this->nfeItemPendingProductId !== null,

                'erp-nfe-lancamento-modal__grid-entry--lookup' => $this->nfeProdutoLookupOpen && filled($this->nfeItemProdutoSearch),

            ])>

                <td class="erp-nfe-lancamento-modal__center erp-nfe-lancamento-modal__entry-marker">*</td>

                <td>

                    <input

                        id="nfe-item-codigo"

                        type="text"

                        wire:model="nfeItemCodigoInput"

                        wire:keydown.enter.prevent="handleNfeItemCodigoEnter"

                        class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--codigo"

                        autocomplete="off"

                    >

                </td>

                <td class="erp-nfe-lancamento-modal__cell-produto">
                    <div class="erp-nfe-produto-field">

                        <input

                            id="nfe-item-produto"

                            type="text"

                            wire:model.live.debounce.250ms="nfeItemProdutoSearch"

                            wire:focus="openNfeProdutoLookup"

                            wire:keydown.enter.prevent="handleNfeItemProdutoEnter"

                            wire:keydown.escape.prevent="closeNfeProdutoLookup"

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--produto"

                            data-erp-uppercase

                            autocomplete="off"

                            placeholder="Descrição"

                        >

                        @if ($this->nfeProdutoLookupOpen && filled($this->nfeItemProdutoSearch))

                            @if ($this->nfeProdutoResults !== [])

                                @include('filament.components.erp.nfe.produto-lookup-panel')

                            @else

                                <div class="erp-nfe-produto-lookup erp-nfe-produto-lookup--empty">

                                    Nenhum produto encontrado.

                                </div>

                            @endif

                        @endif

                    </div>

                </td>

                <td>

                    @if ($this->nfeItemPendingProductId)

                        <input

                            id="nfe-item-cfop"

                            type="text"

                            wire:model="nfeItemEntryCfop"

                            wire:keydown.enter.prevent="advanceNfeEntryField('cfop')"

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"

                            autocomplete="off"

                        >

                    @else

                        <span class="erp-nfe-lancamento-modal__entry-muted">—</span>

                    @endif

                </td>

                <td>

                    @if ($this->nfeItemPendingProductId)

                        <input

                            id="nfe-item-cst"

                            type="text"

                            wire:model="nfeItemEntryCst"

                            wire:keydown.enter.prevent="advanceNfeEntryField('cst')"

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"

                            autocomplete="off"

                        >

                    @else

                        <span class="erp-nfe-lancamento-modal__entry-muted">—</span>

                    @endif

                </td>

                <td>

                    @if ($this->nfeItemPendingProductId)

                        <input

                            id="nfe-item-preco"

                            type="text"

                            wire:model.live.debounce.200ms="nfeItemEntryPreco"

                            wire:keydown.enter.prevent="advanceNfeEntryField('preco')"

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num"

                            autocomplete="off"

                        >

                    @else

                        <span class="erp-nfe-lancamento-modal__entry-muted">—</span>

                    @endif

                </td>

                <td>

                    @if ($this->nfeItemPendingProductId)

                        <input

                            id="nfe-item-quantidade"

                            type="text"

                            wire:model.live.debounce.200ms="nfeItemEntryQtd"

                            wire:keydown.enter.prevent="advanceNfeEntryField('qtd')"

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num"

                            autocomplete="off"

                        >

                    @else

                        <span class="erp-nfe-lancamento-modal__entry-muted">1,0000</span>

                    @endif

                </td>

                <td>

                    @if ($this->nfeItemPendingProductId)

                        <input

                            id="nfe-item-unidade"

                            type="text"

                            wire:model="nfeItemEntryUnidade"

                            wire:keydown.enter.prevent="advanceNfeEntryField('unid')"

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"

                            autocomplete="off"

                        >

                    @else

                        <span class="erp-nfe-lancamento-modal__entry-muted">—</span>

                    @endif

                </td>

                <td class="erp-nfe-lancamento-modal__num">

                    @if ($this->nfeItemPendingProductId)

                        {{ $this->nfeItemEntryTotalDisplay ?: '0,00' }}

                    @else

                        <span class="erp-nfe-lancamento-modal__entry-muted">—</span>

                    @endif

                </td>

                <td class="erp-nfe-lancamento-modal__center erp-nfe-lancamento-modal__entry-muted">—</td>

            </tr>



            @foreach ($this->nfeModalRows as $index => $row)

                <tr

                    wire:key="{{ $row['key'] ?? ('nfe-item-' . $index) }}"

                    wire:click="selectNfeRow({{ $index }})"

                    @class(['erp-lookup-modal__row--selected' => $this->nfeSelectedRowIndex === $index])

                >

                    <td class="erp-nfe-lancamento-modal__center">{{ $row['item'] ?? ($index + 1) }}</td>

                    <td>

                        <input

                            type="text"

                            wire:model.blur="nfeModalRows.{{ $index }}.codigo"

                            wire:keydown.enter.prevent="resolveNfeItemProductFromCodigo({{ $index }})"

                            wire:click.stop

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--codigo"

                            autocomplete="off"

                        >

                    </td>

                    <td>

                        <input

                            type="text"

                            wire:model.blur="nfeModalRows.{{ $index }}.descricao"

                            wire:click.stop

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--produto"

                            autocomplete="off"

                        >

                    </td>

                    <td>

                        <input

                            type="text"

                            wire:model.blur="nfeModalRows.{{ $index }}.cfop"

                            wire:click.stop

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"

                            autocomplete="off"

                        >

                    </td>

                    <td>

                        <input

                            type="text"

                            wire:model.blur="nfeModalRows.{{ $index }}.cst"

                            wire:click.stop

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"

                            autocomplete="off"

                        >

                    </td>

                    <td>

                        <input

                            type="text"

                            wire:model.blur="nfeModalRows.{{ $index }}.valor_unitario"

                            wire:click.stop

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num"

                            autocomplete="off"

                        >

                    </td>

                    <td>

                        <input

                            type="text"

                            wire:model.blur="nfeModalRows.{{ $index }}.quantidade"

                            wire:click.stop

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num"

                            autocomplete="off"

                        >

                    </td>

                    <td>

                        <input

                            type="text"

                            wire:model.blur="nfeModalRows.{{ $index }}.unidade"

                            wire:click.stop

                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"

                            autocomplete="off"

                        >

                    </td>

                    <td class="erp-nfe-lancamento-modal__num">{{ $row['total'] ?? '0,00' }}</td>

                    <td class="erp-nfe-lancamento-modal__center">—</td>

                </tr>

            @endforeach

        </tbody>

    </table>

</div>


