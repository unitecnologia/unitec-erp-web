@include('reports.partials.recibo-document-styles')

<div class="recibo-doc">
    <header class="recibo-doc__header">
        <div class="recibo-doc__brand">
            @if (! empty($logoDataUri) || ! empty($logoUrl))
                <img
                    src="{{ $logoDataUri ?? $logoUrl }}"
                    alt="Logo"
                    class="recibo-doc__logo"
                >
            @endif
            <div>
                <div class="recibo-doc__empresa">{{ mb_strtoupper((string) ($empresa?->razao_social ?? $empresa?->fantasia ?? 'EMPRESA'), 'UTF-8') }}</div>
                @if (filled($empresaEndereco ?? null))
                    <div class="recibo-doc__endereco">{{ $empresaEndereco }}</div>
                @endif
                @if (filled($empresa?->cnpj ?? null))
                    <div class="recibo-doc__meta">CNPJ: {{ $empresa->cnpj }}</div>
                @endif
            </div>
        </div>
        <div class="recibo-doc__title-block">
            <div class="recibo-doc__title">RECIBO</div>
            <div class="recibo-doc__codigo">Nº {{ str_pad((string) $recibo->codigo, 6, '0', STR_PAD_LEFT) }}</div>
        </div>
    </header>

    <div class="recibo-doc__valor-box">
        <span class="recibo-doc__valor-label">Valor</span>
        <span class="recibo-doc__valor">R$ {{ $recibo->valorFormatado() }}</span>
    </div>

    <p class="recibo-doc__texto">
        Recebi(emos) de <strong>{{ mb_strtoupper((string) $recibo->recebi_de, 'UTF-8') }}</strong>
        a importância de <strong>{{ mb_strtolower((string) $extenso, 'UTF-8') }}</strong>
        @if (filled($recibo->referente_a))
            referente a <strong>{{ mb_strtoupper((string) $recibo->referente_a, 'UTF-8') }}</strong>
        @endif
        .
    </p>

    <div class="recibo-doc__rodape">
        <div class="recibo-doc__data">
            {{ ($empresa?->cidade ? mb_strtoupper($empresa->cidade, 'UTF-8').', ' : '') }}{{ optional($recibo->emissao)->format('d/m/Y') }}
        </div>
        <div class="recibo-doc__assinatura">
            <div class="recibo-doc__linha"></div>
            <div class="recibo-doc__assinatura-label">Assinatura</div>
        </div>
    </div>
</div>
