@php
    $valor = number_format((float) ($valor ?? 0), 2, ',', '.');
    $variant = (string) ($variant ?? '');
@endphp
<span @class([
    'erp-fv-mon-totais__box',
    'erp-fv-mon-totais__box--desc' => $variant === 'desc',
    'erp-fv-mon-totais__box--acre' => $variant === 'acre',
    'erp-fv-mon-totais__box--liq' => $variant === 'bold',
]) title="R$ {{ $valor }}">
    <span @class([
        'erp-fv-mon-money',
        'erp-fv-mon-money--summary',
        'erp-fv-mon-money-cell--desc' => $variant === 'desc',
        'erp-fv-mon-money-cell--acre' => $variant === 'acre',
        'erp-fv-mon-money-cell--bold' => $variant === 'bold',
    ])>
        <span class="erp-fv-mon-money__currency">R$</span>
        <span class="erp-fv-mon-money__amount">{{ $valor }}</span>
    </span>
</span>
