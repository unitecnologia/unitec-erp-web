@php
    $empresaSalva = property_exists($this, 'record') && $this->record?->getKey();
@endphp

<div class="erp-empresas-estoques">
    <p class="erp-empresas-parametros__hint">
        Cadastre os depósitos/estoques desta empresa. Opcionalmente vincule um operador responsável —
        o mesmo vínculo fica disponível no cadastro do operador.
    </p>

    @unless ($empresaSalva)
        <p class="erp-empresas-parametros__hint">Salve a empresa primeiro para cadastrar estoques.</p>
    @else
        <div class="erp-empresas-estoques__toolbar">
            <button type="button" class="erp-pcad-actions__btn" wire:click="createEmpresaEstoque">
                <span class="erp-pcad-actions__icon">+</span>
                <span class="erp-pcad-actions__label">Incluir</span>
            </button>
            <button type="button" class="erp-pcad-actions__btn" wire:click="editEmpresaEstoque">
                <span class="erp-pcad-actions__icon">✎</span>
                <span class="erp-pcad-actions__label">Alterar</span>
            </button>
            <button
                type="button"
                class="erp-pcad-actions__btn"
                wire:click="deleteEmpresaEstoque"
                wire:confirm="Excluir o estoque selecionado?"
            >
                <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                <span class="erp-pcad-actions__label">Excluir</span>
            </button>
        </div>

        <div class="erp-empresas-estoques__grid-wrap">
            <table class="erp-empresas-estoques__grid">
                <thead>
                    <tr>
                        <th class="erp-empresas-estoques__col-codigo">Código</th>
                        <th class="erp-empresas-estoques__col-nome">Nome</th>
                        <th class="erp-empresas-estoques__col-vendedor">Vendedor</th>
                        <th class="erp-empresas-estoques__col-ativo">Ativo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->empresaEstoques as $row)
                        <tr
                            wire:key="empresa-estoque-{{ $row['id'] }}"
                            wire:click="selectEmpresaEstoque({{ $row['id'] }})"
                            wire:dblclick="editEmpresaEstoque"
                            @class([
                                'erp-empresas-estoques__row',
                                'erp-empresas-estoques__row--selected' => $this->empresaEstoqueSelectedId === $row['id'],
                            ])
                        >
                            <td class="erp-empresas-estoques__col-codigo">{{ $row['codigo'] }}</td>
                            <td class="erp-empresas-estoques__col-nome" title="{{ $row['nome'] }}">{{ $row['nome'] }}</td>
                            <td class="erp-empresas-estoques__col-vendedor" title="{{ $row['vendedor'] }}">{{ $row['vendedor'] }}</td>
                            <td class="erp-empresas-estoques__col-ativo">{{ $row['ativo'] ? 'Sim' : 'Não' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="erp-empresas-estoques__empty">Nenhum estoque cadastrado para esta empresa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless
</div>

@include('filament.components.erp.empresas.form.estoque-form-modal')
