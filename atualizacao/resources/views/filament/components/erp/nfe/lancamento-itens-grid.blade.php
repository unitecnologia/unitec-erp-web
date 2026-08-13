<div
    class="erp-lookup-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap erp-nfe-lancamento-modal__grid-wrap--itens"
    tabindex="-1"
    wire:keydown.arrow-up.prevent="moveNfeSelectedRow(-1)"
    wire:keydown.arrow-down.prevent="moveNfeSelectedRow(1)"
    x-data
    @keydown.enter="
        const el = $event.target;
        if (
            (! (el instanceof HTMLInputElement) && ! (el instanceof HTMLSelectElement))
            || el.disabled
        ) {
            return;
        }
        if (! el.hasAttribute('data-erp-nfe-itens-enter')) {
            return;
        }

        $event.preventDefault();
        $event.stopPropagation();

        const collect = () => Array.from($el.querySelectorAll(
            'input[data-erp-nfe-itens-enter]:not([disabled]), select[data-erp-nfe-itens-enter]:not([disabled])'
        )).filter((field) => field.offsetParent !== null);

        const fields = collect();
        const idx = fields.indexOf(el);
        if (idx < 0) {
            return;
        }
        const nextIdx = idx + 1 < fields.length ? idx + 1 : 0;
        const rowIndex = el.getAttribute('data-erp-nfe-itens-row');
        const isCfop = el.hasAttribute('data-erp-nfe-itens-enter-cfop');
        const cfopValue = el.value;

        el.blur();

        const tryFocus = (attempt = 0) => {
            const fresh = collect();
            const next = fresh[nextIdx] ?? null;
            if (! next) {
                return;
            }
            next.focus();
            if (document.activeElement === next || attempt >= 12) {
                if (next instanceof HTMLInputElement && ! next.readOnly) {
                    next.select();
                }
                return;
            }
            setTimeout(() => tryFocus(attempt + 1), 30 + (attempt * 20));
        };

        if (isCfop && rowIndex !== null) {
            $wire.resolveNfeItemCfop(Number(rowIndex), cfopValue).then(() => {
                setTimeout(() => tryFocus(0), 0);
            });
            return;
        }

        setTimeout(() => tryFocus(0), 0);
    "
>
    <table class="erp-lookup-modal__grid erp-nfe-lancamento-modal__grid erp-nfe-lancamento-modal__grid--itens">
        <colgroup>
            <col class="erp-nfe-col-item">
            <col class="erp-nfe-col-codigo">
            <col class="erp-nfe-col-barra">
            <col class="erp-nfe-col-ref">
            <col class="erp-nfe-col-produto">
            <col class="erp-nfe-col-cfop">
            <col class="erp-nfe-col-cst">
            <col class="erp-nfe-col-preco">
            <col class="erp-nfe-col-desc">
            <col class="erp-nfe-col-acre">
            <col class="erp-nfe-col-qtd">
            <col class="erp-nfe-col-unid">
            <col class="erp-nfe-col-total">
            <col class="erp-nfe-col-pedido">
        </colgroup>
        <thead>
            <tr>
                <th>ITEM</th>
                <th>CÓDIGO</th>
                <th>COD. BARRAS</th>
                <th>REFERÊNCIA</th>
                <th>PRODUTO</th>
                <th class="erp-nfe-lancamento-modal__center">CFOP</th>
                <th class="erp-nfe-lancamento-modal__center">CST</th>
                <th class="erp-nfe-lancamento-modal__num">PREÇO</th>
                <th class="erp-nfe-lancamento-modal__num">DESC.</th>
                <th class="erp-nfe-lancamento-modal__num">ACRE.</th>
                <th class="erp-nfe-lancamento-modal__num">QTD.</th>
                <th class="erp-nfe-lancamento-modal__center">UNID.</th>
                <th class="erp-nfe-lancamento-modal__num">TOTAL</th>
                <th class="erp-nfe-lancamento-modal__center">PEDIDO</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->nfeModalRows as $index => $row)
                <tr
                    wire:key="{{ $row['key'] ?? ('nfe-item-' . $index) }}"
                    wire:click="selectNfeRow({{ $index }})"
                    x-on:click="
                        if ($event.target.closest('input, select, textarea, button')) return;
                        $nextTick(() => $el.closest('.erp-nfe-lancamento-modal__grid-wrap--itens')?.focus({ preventScroll: true }))
                    "
                    data-erp-nfe-item-index="{{ $index }}"
                    @class([
                        'erp-nfe-lancamento-modal__row',
                        'erp-nfe-lancamento-modal__row--selected' => $this->nfeSelectedRowIndex === $index,
                        'erp-lookup-modal__row--selected' => $this->nfeSelectedRowIndex === $index,
                    ])
                >
                    <td>
                        <div class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--readonly erp-nfe-lancamento-modal__cell-input--center">
                            {{ $row['item'] ?? ($index + 1) }}
                        </div>
                    </td>
                    <td>
                        <div
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--readonly erp-nfe-lancamento-modal__cell-input--codigo"
                            title="{{ $row['codigo'] ?? '' }}"
                        >{{ $row['codigo'] ?? '' }}</div>
                    </td>
                    <td>
                        <div
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--readonly erp-nfe-lancamento-modal__cell-input--barra"
                            title="{{ filled($row['cod_barra'] ?? null) ? $row['cod_barra'] : '—' }}"
                        >{{ filled($row['cod_barra'] ?? null) ? $row['cod_barra'] : '—' }}</div>
                    </td>
                    <td>
                        <div
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--readonly erp-nfe-lancamento-modal__cell-input--center"
                            title="{{ filled($row['referencia'] ?? null) ? $row['referencia'] : '—' }}"
                        >{{ filled($row['referencia'] ?? null) ? $row['referencia'] : '—' }}</div>
                    </td>
                    <td>
                        <div
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--readonly erp-nfe-lancamento-modal__cell-input--desc erp-nfe-lancamento-modal__cell-input--pull"
                            title="Duplo clique: voltar para a barra de inclusão"
                            wire:dblclick.stop="puxarNfeItemParaInclusao({{ $index }})"
                        >{{ $row['descricao'] ?? '' }}</div>
                    </td>
                    <td>
                        <input
                            type="text"
                            wire:model.blur="nfeModalRows.{{ $index }}.cfop"
                            wire:click.stop
                            data-erp-nfe-itens-enter
                            data-erp-nfe-itens-enter-cfop
                            data-erp-nfe-itens-row="{{ $index }}"
                            x-on:focus="$el.select()"
                            x-on:click.stop="$el.select()"
                            @mouseup.prevent
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"
                            autocomplete="off"
                            title="Digite o CFOP e pressione Enter"
                        >
                    </td>
                    <td>
                        <input
                            type="text"
                            wire:model.blur="nfeModalRows.{{ $index }}.cst"
                            wire:click.stop
                            data-erp-nfe-itens-enter
                            x-on:focus="$el.select()"
                            x-on:click.stop="$el.select()"
                            @mouseup.prevent
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"
                            autocomplete="off"
                        >
                    </td>
                    <td>
                        <div class="erp-nfe-lancamento-modal__money erp-nfe-lancamento-modal__money--field">
                            <span class="erp-nfe-lancamento-modal__money-rs">R$</span>
                            <input
                                type="text"
                                wire:model.blur="nfeModalRows.{{ $index }}.valor_unitario"
                                wire:click.stop
                                data-erp-nfe-itens-enter
                                x-on:focus="$el.select()"
                                x-on:click.stop="$el.select()"
                                @mouseup.prevent
                                class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num erp-nfe-lancamento-modal__money-input"
                                autocomplete="off"
                            >
                        </div>
                    </td>
                    <td>
                        <div class="erp-nfe-lancamento-modal__money erp-nfe-lancamento-modal__money--field">
                            <span class="erp-nfe-lancamento-modal__money-rs">R$</span>
                            <input
                                type="text"
                                wire:model.blur="nfeModalRows.{{ $index }}.desconto"
                                wire:click.stop
                                data-erp-nfe-itens-enter
                                x-on:focus="$el.select()"
                                x-on:click.stop="$el.select()"
                                @mouseup.prevent
                                class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num erp-nfe-lancamento-modal__money-input"
                                autocomplete="off"
                                title="Desconto do item"
                            >
                        </div>
                    </td>
                    <td>
                        <div class="erp-nfe-lancamento-modal__money erp-nfe-lancamento-modal__money--field">
                            <span class="erp-nfe-lancamento-modal__money-rs">R$</span>
                            <input
                                type="text"
                                wire:model.blur="nfeModalRows.{{ $index }}.outros"
                                wire:click.stop
                                data-erp-nfe-itens-enter
                                x-on:focus="$el.select()"
                                x-on:click.stop="$el.select()"
                                @mouseup.prevent
                                class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num erp-nfe-lancamento-modal__money-input"
                                autocomplete="off"
                                title="Acréscimo do item"
                            >
                        </div>
                    </td>
                    <td>
                        <input
                            type="text"
                            wire:model.blur="nfeModalRows.{{ $index }}.quantidade"
                            wire:click.stop
                            data-erp-nfe-itens-enter
                            x-on:focus="$el.select()"
                            x-on:click.stop="$el.select()"
                            @mouseup.prevent
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num"
                            autocomplete="off"
                        >
                    </td>
                    <td>
                        <input
                            type="text"
                            wire:model.blur="nfeModalRows.{{ $index }}.unidade"
                            wire:click.stop
                            data-erp-nfe-itens-enter
                            x-on:focus="$el.select()"
                            x-on:click.stop="$el.select()"
                            @mouseup.prevent
                            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--center"
                            autocomplete="off"
                        >
                    </td>
                    <td>
                        <div class="erp-nfe-lancamento-modal__money erp-nfe-lancamento-modal__money--field">
                            <span class="erp-nfe-lancamento-modal__money-rs">R$</span>
                            <input
                                type="text"
                                value="{{ $row['total'] ?? '0,000' }}"
                                readonly
                                tabindex="0"
                                wire:click.stop
                                data-erp-nfe-itens-enter
                                x-on:focus="$el.select()"
                                x-on:click.stop="$el.select()"
                                @mouseup.prevent
                                class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num erp-nfe-lancamento-modal__money-input erp-nfe-lancamento-modal__cell-input--readonly"
                                title="Total do item (somente leitura)"
                                autocomplete="off"
                            >
                        </div>
                    </td>
                    <td>
                        <div class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--readonly erp-nfe-lancamento-modal__cell-input--center"
                             title="{{ filled($row['pedido'] ?? null) ? $row['pedido'] : (filled($this->nfeForm['numero_pedido'] ?? null) ? $this->nfeForm['numero_pedido'] : '') }}">
                            @php
                                $pedidoItem = trim((string) ($row['pedido'] ?? ''));
                                if ($pedidoItem === '') {
                                    $pedidoItem = trim((string) ($this->nfeForm['numero_pedido'] ?? ''));
                                }
                            @endphp
                            {{ $pedidoItem !== '' ? $pedidoItem : '—' }}
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="erp-lookup-modal__empty">
                        Nenhum item. Informe código, barras ou nome na barra Produto acima e pressione Enter.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
