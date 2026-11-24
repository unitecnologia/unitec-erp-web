@php
    $meios = $this->caixaMeiosResumo;
    $linhas = $meios['linhas'] ?? [];
    $fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
    $fmtNeg = static function (float $v) use ($fmt): string {
        return $v > 0.009 ? '−'.$fmt($v) : $fmt(0);
    };
    $visivel = ! empty($meios['visivel']);
    $outras = (float) ($meios['outras_saidas'] ?? 0);
    $saldoLiq = (float) ($meios['saldo_liquido'] ?? 0);
@endphp
<div class="erp-caixa__meios" data-erp-caixa-meios role="status" aria-live="polite" @if (! $visivel) hidden @endif>
    <span class="erp-caixa__meios-title">RECEBIDOS</span>
    <span class="erp-caixa__meios-items" data-erp-caixa-meios-items>
        @forelse ($linhas as $linha)
            <span class="erp-caixa__meios-item {{ ($linha['forma'] ?? '') === 'SEM MEIO' ? 'is-sem-meio' : '' }}">
                <span class="erp-caixa__meios-forma">{{ $linha['forma'] }}</span>
                <strong class="erp-caixa__meios-valor">{{ $fmt((float) ($linha['recebido'] ?? 0)) }}</strong>
            </span>
        @empty
            <span class="erp-caixa__meios-item is-empty">—</span>
        @endforelse
    </span>
    <span class="erp-caixa__meios-sep" aria-hidden="true"></span>
    <span class="erp-caixa__meios-item is-entradas" title="Soma das entradas do período/filtros (inclui sem meio)">
        <span class="erp-caixa__meios-forma">ENTRADAS</span>
        <strong class="erp-caixa__meios-valor" data-erp-caixa-meios-entradas>
            {{ $fmt((float) ($meios['total_entradas'] ?? 0)) }}
        </strong>
    </span>
    <span class="erp-caixa__meios-item is-estorno" title="Estornos de recebimentos no período/filtros">
        <span class="erp-caixa__meios-forma">ESTORNOS</span>
        <strong class="erp-caixa__meios-valor" data-erp-caixa-meios-estorno>
            {{ $fmtNeg((float) ($meios['total_estorno'] ?? 0)) }}
        </strong>
    </span>
    <span class="erp-caixa__meios-item is-outras-saidas" title="Saídas do período/filtros que não são estornos (despesas, pagamentos, transferências de saída)">
        <span class="erp-caixa__meios-forma">OUTRAS SAÍDAS</span>
        <strong class="erp-caixa__meios-valor" data-erp-caixa-meios-outras>
            {{ $fmtNeg($outras) }}
        </strong>
    </span>
    <span class="erp-caixa__meios-item is-saldo-liquido" title="Entradas − estornos − outras saídas (período/filtros). Não é o saldo total da conta.">
        <span class="erp-caixa__meios-forma">SALDO</span>
        <strong class="erp-caixa__meios-valor {{ $saldoLiq < -0.009 ? 'is-negativo' : '' }}" data-erp-caixa-meios-saldo>
            {{ $fmt($saldoLiq) }}
        </strong>
    </span>
</div>
