<div class="erp-operacoes-fiscais__cfop-lookup">
    @forelse ($this->cfopResultados as $cfop)
        <button
            type="button"
            wire:key="operacao-fiscal-cfop-{{ $this->cfopLookupCampo }}-{{ $cfop['codigo'] }}"
            wire:click="selecionarCfop({{ $cfop['codigo'] }})"
        >
            <strong>{{ $cfop['codigo'] }}</strong>
            <span>{{ $cfop['descricao'] }}</span>
        </button>
    @empty
        <div class="erp-operacoes-fiscais__cfop-empty">Nenhum CFOP cadastrado encontrado.</div>
    @endforelse
</div>
