@php
    $empresaNome = mb_strtoupper(trim((string) ($empresa?->fantasia ?: $empresa?->razao_social ?: $empresa?->nome ?: 'EMPRESA')), 'UTF-8');
    $empresaCnpj = filled($empresa?->cnpj ?? null) ? trim((string) $empresa->cnpj) : '';
    $empresaFone = filled($empresa?->telefone ?? null) ? trim((string) $empresa->telefone) : '';
    $cidade = filled($empresa?->cidade ?? null) ? mb_strtoupper(trim((string) $empresa->cidade), 'UTF-8') : '';
    $uf = filled($empresa?->uf ?? null) ? mb_strtoupper(trim((string) $empresa->uf), 'UTF-8') : '';
    $dataLinha = trim(($cidade !== '' ? $cidade.($uf !== '' ? '-'.$uf : '').', ' : '').(optional($recibo->emissao)->format('d/m/Y') ?? ''));
@endphp

<div class="recibo-doc">
    <div class="recibo-doc__frame">
        <div class="recibo-doc__header">
            <div class="recibo-doc__brand">
                @if (! empty($logoDataUri) || ! empty($logoUrl))
                    <img
                        src="{{ $logoDataUri ?? $logoUrl }}"
                        alt="Logo"
                        class="recibo-doc__logo"
                    >
                @endif
                <div class="recibo-doc__empresa">{{ $empresaNome }}</div>
                @if (filled($empresaEndereco ?? null))
                    <p class="recibo-doc__meta">{{ $empresaEndereco }}</p>
                @endif
                @if ($empresaCnpj !== '')
                    <p class="recibo-doc__meta">CNPJ: {{ $empresaCnpj }}</p>
                @endif
                @if ($empresaFone !== '')
                    <p class="recibo-doc__meta">Fone: {{ $empresaFone }}</p>
                @endif
            </div>
            <div class="recibo-doc__title-block">
                <div class="recibo-doc__title">RECIBO</div>
                <div class="recibo-doc__codigo">Nº {{ str_pad((string) $recibo->codigo, 6, '0', STR_PAD_LEFT) }}</div>
            </div>
        </div>

        <hr class="recibo-doc__rule">

        <div class="recibo-doc__valor-box">
            <div class="recibo-doc__valor-label">VALOR</div>
            <div class="recibo-doc__valor">R$ {{ $recibo->valorFormatado() }}</div>
        </div>

        <p class="recibo-doc__texto">
            Recebi(emos) de <strong>{{ mb_strtoupper((string) $recibo->recebi_de, 'UTF-8') }}</strong>
            a importância de <strong>{{ mb_strtoupper(trim((string) $extenso), 'UTF-8') }}</strong>
            @if (filled($recibo->referente_a))
                referente a <strong>{{ mb_strtoupper((string) $recibo->referente_a, 'UTF-8') }}</strong>
            @endif.
        </p>

        <p class="recibo-doc__data">{{ $dataLinha }}</p>

        <div class="recibo-doc__assinatura">
            <div class="recibo-doc__linha"></div>
            Assinatura
        </div>
    </div>
</div>
