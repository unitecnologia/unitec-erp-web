<table class="erp-nfe-produto-lookup__table">
    <thead>
        <tr>
            <th>Cód.</th>
            <th>Descrição</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($this->nfeProdutoResults as $index => $row)
            <tr
                wire:key="nfe-produto-row-{{ $index }}-{{ $row['id'] }}"
                wire:click="selectNfeProdutoResult({{ $index }}, true)"
                id="nfe-produto-row-{{ $index }}"
                data-nfe-produto-index="{{ $index }}"
                @class([
                    'erp-nfe-produto-lookup__row',
                    'erp-nfe-produto-lookup__row--active' => (int) $this->nfeSelectedProdutoIndex === (int) $index,
                ])
            >
                <td>{{ $row['codigo'] ?: '—' }}</td>
                <td class="erp-nfe-produto-lookup__descricao">{{ $row['descricao'] ?: '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
