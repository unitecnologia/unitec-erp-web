@php
    use App\Models\NotaFornecedor;

    $statusTabs = [
        'todas' => 'Todas',
        NotaFornecedor::STATUS_PENDENTE => 'Pendentes',
        NotaFornecedor::STATUS_GEROU_COMPRAS => 'Gerou Compras',
        NotaFornecedor::STATUS_ACEITA => 'Aceitas',
        NotaFornecedor::STATUS_DESCONHECIDA => 'Desconhecidas',
    ];

    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'todas';
@endphp

<div
    class="erp-nfe__tabs-wrap erp-list-tabs"
    wire:ignore
    x-data="{ statusAtivo: @js($statusAtual) }"
>
    <div class="erp-nfe__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                @class([
                    'erp-nfe__tab',
                    'erp-nfe__tab--' . $value,
                ])
                :class="{ 'erp-nfe__tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); $wire.setStatusFilter(@js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
