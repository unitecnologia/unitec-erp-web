@php
    $canUpdate = \App\Support\Erp\ErpAccess::currentCan('ajusta_preco.update');
    $rows = $this->precosPainel;
@endphp

<div class="erp-ajusta-precos__prices">
    <div class="erp-ajusta-precos__section-bar" role="heading" aria-level="2">Preços</div>

    <div class="erp-ajusta-precos__prices-table-wrap">
        <table class="erp-ajusta-precos__prices-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>% Lucro Varejo</th>
                    <th>Preço Varejo</th>
                    <th>% Lucro Atacado</th>
                    <th>Preço Atacado</th>
                    <th>% Lucro Especial</th>
                    <th>Preço Especial</th>
                    <th>Origem</th>
                    <th>CSOSN</th>
                    <th>CST</th>
                    <th>% ICMS</th>
                    @if ($canUpdate)
                        <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $index => $row)
                    <tr wire:key="ajp-price-{{ $row['product_id'] }}-{{ $row['empresa_id'] }}-{{ $index }}">
                        <td class="erp-ajusta-precos__prices-empresa">{{ $row['empresa'] }}</td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.{{ $index }}.pct_lucro" @disabled(! $canUpdate)>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.{{ $index }}.preco_venda" @disabled(! $canUpdate)>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.{{ $index }}.pct_lucro_atacado" @disabled(! $canUpdate) title="Calculado a partir do custo; ao salvar usa o preço atacado">
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.{{ $index }}.preco_atacado" @disabled(! $canUpdate)>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.{{ $index }}.pct_lucro_especial" @disabled(! $canUpdate) title="Calculado a partir do custo; ao salvar usa o preço especial">
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.{{ $index }}.preco_especial" @disabled(! $canUpdate)>
                        </td>
                        <td>
                            <input type="text" maxlength="1" class="erp-ajusta-precos__prices-input erp-ajusta-precos__prices-input--xs" wire:model="precosPainel.{{ $index }}.origem" @disabled(! $canUpdate)>
                        </td>
                        <td>
                            <input type="text" maxlength="3" class="erp-ajusta-precos__prices-input erp-ajusta-precos__prices-input--sm" wire:model="precosPainel.{{ $index }}.csosn" @disabled(! $canUpdate)>
                        </td>
                        <td>
                            <input type="text" maxlength="3" class="erp-ajusta-precos__prices-input erp-ajusta-precos__prices-input--sm" wire:model="precosPainel.{{ $index }}.cst_icms" @disabled(! $canUpdate)>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input erp-ajusta-precos__prices-input--sm" wire:model="precosPainel.{{ $index }}.aliq_icms" @disabled(! $canUpdate)>
                        </td>
                        @if ($canUpdate)
                            <td>
                                <button
                                    type="button"
                                    class="erp-ajusta-precos__prices-save"
                                    wire:click="salvarPrecoPainel({{ $index }})"
                                    title="Salvar preços desta linha"
                                >
                                    Salvar
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr class="erp-ajusta-precos__prices-empty-row">
                        <td colspan="{{ $canUpdate ? 12 : 11 }}">
                            <span class="erp-ajusta-precos__prices-empty">Selecione um produto na grade para ver e editar os preços.</span>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
