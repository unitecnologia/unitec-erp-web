@php
    $contas = $this->boletoContasApiRows;
    $empresaSalva = property_exists($this, 'record') && $this->record?->getKey();
@endphp

<div class="erp-empresas-boleto">
    <div class="erp-empresas-boleto__header">
        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">
            <label class="erp-pcad__check">
                <input type="checkbox" wire:model="data.param_boleto_habilitar">
                <span>Habilitar API Boleto</span>
            </label>
        </div>
        <p class="erp-empresas-parametros__hint">
            Cadastre uma ou mais contas (Ailos e/ou Sicredi). Na geração do boleto o operador escolhe a conta
            se houver mais de uma. Depois de emitido, o banco fica travado.
        </p>
    </div>

    @unless ($empresaSalva)
        <p class="erp-empresas-parametros__hint">Salve a empresa primeiro para cadastrar contas de cobrança.</p>
    @else
        <div class="erp-empresas-boleto__toolbar">
            <button type="button" class="erp-empresas-boleto__btn-nova" wire:click="openBoletoContaCreate">
                + Nova conta
            </button>
        </div>

        <div class="erp-empresas-boleto__grid-wrap">
            <table class="erp-empresas-boleto__grid">
                <thead>
                    <tr>
                        <th>Conta</th>
                        <th class="erp-empresas-boleto__col-banco">Banco</th>
                        <th class="erp-empresas-boleto__col-ambiente">Ambiente</th>
                        <th class="erp-empresas-boleto__col-status">Status</th>
                        <th class="erp-empresas-boleto__col-acoes">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contas as $row)
                        <tr wire:key="boleto-conta-{{ $row['id'] }}">
                            <td>
                                {{ $row['rotulo'] }}
                                @if ($row['padrao'])
                                    <span class="erp-empresas-boleto__badge">Padrão</span>
                                @endif
                            </td>
                            <td class="erp-empresas-boleto__col-banco">{{ $row['banco'] }}</td>
                            <td class="erp-empresas-boleto__col-ambiente">
                                {{ ($row['ambiente'] ?? '') === 'producao' ? 'Produção' : 'Homologação' }}
                            </td>
                            <td class="erp-empresas-boleto__col-status">{{ $row['ativo'] ? 'Ativa' : 'Inativa' }}</td>
                            <td class="erp-empresas-boleto__col-acoes">
                                <div class="erp-empresas-boleto__acoes">
                                    <button type="button" class="erp-empresas-boleto__link" wire:click="openBoletoContaEdit({{ $row['id'] }})">Editar</button>
                                    @if (! $row['padrao'])
                                        <button type="button" class="erp-empresas-boleto__link" wire:click="marcarBoletoContaPadrao({{ $row['id'] }})">Padrão</button>
                                    @endif
                                    <button
                                        type="button"
                                        class="erp-empresas-boleto__link erp-empresas-boleto__link--danger"
                                        wire:click="excluirBoletoConta({{ $row['id'] }})"
                                        wire:confirm="Excluir esta conta de cobrança?"
                                    >Excluir</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="erp-empresas-boleto__empty">Nenhuma conta cadastrada ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless
</div>
