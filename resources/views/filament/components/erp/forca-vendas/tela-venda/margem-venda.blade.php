@if ($this->margemModalOpen)
    @php
        $fmtMoney = fn (float $v): string => number_format($v, 2, ',', '.');
        $fmtQtd = fn (float $v): string => number_format($v, 3, ',', '.');
        $fmtPct = fn (float $v): string => number_format($v, 2, ',', '.').'%';
        $totais = $this->margemTotais;
        $colValorVendido = $margemColValorVendido ?? 'Vlr. venda';
    @endphp
    <div class="erp-pdv-modal erp-fv-tv-margem" role="dialog" aria-modal="true" aria-labelledby="erp-fv-margem-title" wire:keydown.escape.window="fecharMargemVenda">
        <div class="erp-pdv-modal__backdrop" wire:click="fecharMargemVenda"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide erp-fv-tv-margem__window">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-fv-margem-title">Margem da venda</h2>
            </header>
            <div class="erp-pdv-modal__body erp-fv-tv-margem__body">
                <p class="erp-fv-tv-margem__hint">Valores calculados com o custo atual dos produtos.</p>

                <div class="erp-fv-tv-margem__scroll">
                    <table class="erp-fv-tv-margem__table">
                        <thead>
                            <tr>
                                <th class="erp-fv-tv-margem__th--code">Código</th>
                                <th class="erp-fv-tv-margem__th--prod">Produto</th>
                                <th class="erp-fv-tv-margem__th--num">Qtd</th>
                                <th class="erp-fv-tv-margem__th--money">{{ $colValorVendido }}</th>
                                <th class="erp-fv-tv-margem__th--money">Desc./Acr.</th>
                                <th class="erp-fv-tv-margem__th--money">Líquido</th>
                                <th class="erp-fv-tv-margem__th--money">Custo</th>
                                <th class="erp-fv-tv-margem__th--money erp-fv-tv-margem__th--lucro">Lucro estimado</th>
                                <th class="erp-fv-tv-margem__th--pct">Margem %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->margemLinhas as $linha)
                                <tr>
                                    <td class="erp-fv-tv-margem__td--code">{{ $linha['codigo'] ?? '' }}</td>
                                    <td class="erp-fv-tv-margem__td--prod" title="{{ $linha['produto'] }}">{{ $linha['produto'] }}</td>
                                    <td class="erp-fv-tv-margem__td--num">{{ $fmtQtd((float) $linha['qtd']) }}</td>
                                    <td class="erp-fv-tv-margem__td--money">{{ $fmtMoney((float) $linha['valor_vendido']) }}</td>
                                    <td @class([
                                        'erp-fv-tv-margem__td--money',
                                        'erp-fv-tv-margem__td--neg' => (float) $linha['desc_acr'] < 0,
                                        'erp-fv-tv-margem__td--pos' => (float) $linha['desc_acr'] > 0,
                                    ])>{{ $fmtMoney((float) $linha['desc_acr']) }}</td>
                                    <td class="erp-fv-tv-margem__td--money">{{ $fmtMoney((float) $linha['liquido']) }}</td>
                                    <td class="erp-fv-tv-margem__td--money">{{ $fmtMoney((float) $linha['custo']) }}</td>
                                    <td @class([
                                        'erp-fv-tv-margem__td--money',
                                        'erp-fv-tv-margem__td--lucro',
                                        'erp-fv-tv-margem__td--neg' => (float) $linha['lucro'] < 0,
                                        'erp-fv-tv-margem__td--pos' => (float) $linha['lucro'] > 0,
                                    ])>{{ $fmtMoney((float) $linha['lucro']) }}</td>
                                    <td @class([
                                        'erp-fv-tv-margem__td--pct',
                                        'erp-fv-tv-margem__td--neg' => (float) $linha['margem'] < 0,
                                        'erp-fv-tv-margem__td--pos' => (float) $linha['margem'] > 0,
                                    ])>{{ $fmtPct((float) $linha['margem']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="erp-fv-tv-margem__empty">Nenhum item na venda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="erp-fv-tv-margem__totais" role="group" aria-label="Totais da margem">
                    <div class="erp-fv-tv-margem__total">
                        <span class="erp-fv-tv-margem__total-label">Venda líquida</span>
                        <strong class="erp-fv-tv-margem__total-value">R$ {{ $fmtMoney((float) ($totais['venda_liquida'] ?? 0)) }}</strong>
                    </div>
                    <div class="erp-fv-tv-margem__total">
                        <span class="erp-fv-tv-margem__total-label">Custo total</span>
                        <strong class="erp-fv-tv-margem__total-value">R$ {{ $fmtMoney((float) ($totais['custo_total'] ?? 0)) }}</strong>
                    </div>
                    <div class="erp-fv-tv-margem__total erp-fv-tv-margem__total--lucro">
                        <span class="erp-fv-tv-margem__total-label">Lucro estimado</span>
                        <strong @class([
                            'erp-fv-tv-margem__total-value',
                            'erp-fv-tv-margem__td--neg' => (float) ($totais['lucro_estimado'] ?? 0) < 0,
                            'erp-fv-tv-margem__td--pos' => (float) ($totais['lucro_estimado'] ?? 0) > 0,
                        ])>R$ {{ $fmtMoney((float) ($totais['lucro_estimado'] ?? 0)) }}</strong>
                    </div>
                    <div class="erp-fv-tv-margem__total erp-fv-tv-margem__total--margem">
                        <span class="erp-fv-tv-margem__total-label">Margem</span>
                        <strong @class([
                            'erp-fv-tv-margem__total-value',
                            'erp-fv-tv-margem__td--neg' => (float) ($totais['margem'] ?? 0) < 0,
                            'erp-fv-tv-margem__td--pos' => (float) ($totais['margem'] ?? 0) > 0,
                        ])>{{ $fmtPct((float) ($totais['margem'] ?? 0)) }}</strong>
                    </div>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button
                    type="button"
                    wire:click="fecharMargemVenda"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                    autofocus
                >Fechar</button>
            </footer>
        </div>
    </div>
@endif
