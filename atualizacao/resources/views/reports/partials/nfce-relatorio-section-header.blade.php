<div class="nfce-relatorio-doc__header-box">
    <div class="nfce-relatorio-doc__header">
        @if ($fantasia !== '')
            <span class="nfce-relatorio-doc__fantasia">{{ $fantasia }}</span>
        @endif
        @if ($razao !== '' && $razao !== $fantasia)
            <span class="nfce-relatorio-doc__razao">{{ $razao }}</span>
        @endif
        @if (filled($empresaEnderecoLinha1))
            <span class="nfce-relatorio-doc__address">END: {{ $empresaEnderecoLinha1 }}</span>
        @endif
        @if (filled($empresaEnderecoLinha2))
            <span class="nfce-relatorio-doc__address">{{ $empresaEnderecoLinha2 }}</span>
        @endif
        <span class="nfce-relatorio-doc__contact">
            FONE: {{ $empresa?->telefone ?: '' }}&nbsp;&nbsp;EMAIL: {{ $empresa?->email ?: '' }}
        </span>
    </div>
</div>

<div class="nfce-relatorio-doc__title-box">
    <div class="nfce-relatorio-doc__title">{{ $sectionTitle }}</div>
</div>

<div class="nfce-relatorio-doc__filter-box">
    <span>| SITUAÇÃO --> {{ $statusLabel }}</span>
    @if ($periodLabel)
        <span>| {{ mb_strtoupper($periodLabel, 'UTF-8') }}</span>
    @endif
</div>
