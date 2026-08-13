@php
    use App\Models\NotaFornecedor;

    $statusTabs = [
        'todas' => 'Todas',
        NotaFornecedor::STATUS_PENDENTE => 'Pendentes',
        NotaFornecedor::STATUS_GEROU_COMPRAS => 'Gerou Compras',
        NotaFornecedor::STATUS_ACEITA => 'Aceitas',
        NotaFornecedor::STATUS_DESCONHECIDA => 'Desconhecidas',
    ];
@endphp

<div class="erp-nfe__tabs-wrap">
    <div class="erp-nfe__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                @class([
                    'erp-nfe__tab',
                    'erp-nfe__tab--' . $value,
                    'erp-nfe__tab--active' => $this->statusFilter === $value,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
