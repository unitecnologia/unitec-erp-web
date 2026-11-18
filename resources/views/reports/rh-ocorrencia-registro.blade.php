<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Registro de ocorrência — RH</title>
    <style>
        @page { margin: 12mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #c7d5e8; font-family: Arial, Helvetica, sans-serif; color: #0f172a; }
        .viewer { min-height: 100vh; display: flex; flex-direction: column; }
        .viewer__toolbar { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; padding: 0.35rem 0.5rem; background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%); border-bottom: 1px solid #94a3b8; }
        .viewer__title { margin-right: auto; font-size: 0.82rem; font-weight: 700; color: #0f2847; }
        .viewer__btn { min-width: 5.5rem; padding: 0.28rem 0.65rem; border: 1px solid #94a3b8; border-radius: 4px; background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%); color: #0f172a; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
        .viewer__btn--close { background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%); border-color: #fca5a5; }
        .viewer__canvas { overflow: auto; padding: 1rem; flex: 1; }
        .paper { width: min(210mm, 100%); margin: 0 auto; background: #fff; border: 1px solid #94a3b8; padding: 12mm 12mm 10mm; }
        .hdr { display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.75rem; }
        .logo { width: 64px; height: 64px; object-fit: contain; }
        .company-name { font-size: 1rem; font-weight: 800; text-transform: uppercase; }
        .meta { font-size: 0.78rem; color: #334155; margin-top: 0.15rem; }
        .title { text-align: center; font-size: 1.05rem; font-weight: 800; margin: 0.85rem 0 0.35rem; letter-spacing: 0.02em; }
        .sub { text-align: center; font-size: 0.78rem; color: #475569; margin-bottom: 0.85rem; }
        .box { border: 1px solid #cbd5e1; border-radius: 4px; padding: 0.65rem 0.75rem; margin-bottom: 0.65rem; }
        .box h3 { margin: 0 0 0.4rem; font-size: 0.78rem; text-transform: uppercase; color: #1e5a9e; }
        .row { display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.86rem; line-height: 1.35; }
        .row strong { font-weight: 700; }
        .desc { white-space: pre-wrap; font-size: 0.88rem; line-height: 1.45; margin: 0.25rem 0 0; }
        .note { margin-top: 1rem; font-size: 0.72rem; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 0.5rem; }
        @media print {
            body { background: #fff; }
            .viewer__toolbar { display: none; }
            .viewer__canvas { padding: 0; }
            .paper { border: none; width: 100%; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="viewer">
        <div class="viewer__toolbar">
            <span class="viewer__title">Registro de ocorrência — código {{ $ocorrencia->codigo }}</span>
            <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
            <button type="button" class="viewer__btn viewer__btn--close" onclick="window.location.href=@js($closeUrl)">Fechar</button>
        </div>
        <div class="viewer__canvas">
            <div class="paper">
                <div class="hdr">
                    @if ($logoDataUri)
                        <img class="logo" src="{{ $logoDataUri }}" alt="">
                    @elseif ($logoUrl)
                        <img class="logo" src="{{ $logoUrl }}" alt="">
                    @endif
                    <div>
                        <div class="company-name">{{ mb_strtoupper((string) ($empresa?->razao_social ?: $empresa?->fantasia ?: $empresa?->nome ?: 'EMPRESA'), 'UTF-8') }}</div>
                        @if (filled($empresaDoc))
                            <div class="meta">{{ $empresaDoc }}</div>
                        @endif
                        @if (filled($empresaEndereco))
                            <div class="meta">{{ $empresaEndereco }}</div>
                        @endif
                    </div>
                </div>

                <div class="title">REGISTRO DE OCORRÊNCIA — RH</div>
                <div class="sub">Código {{ $ocorrencia->codigo }} | {{ $ocorrencia->situacaoLabel() }}</div>

                <div class="box">
                    <h3>Funcionário</h3>
                    <div class="row">
                        <div><strong>Código:</strong> {{ $ocorrencia->funcionario?->codigo ?? '—' }}</div>
                        <div><strong>Nome:</strong> {{ $ocorrencia->funcionario?->nome ?? '—' }}</div>
                    </div>
                </div>

                <div class="box">
                    <h3>Ocorrência</h3>
                    <div class="row">
                        <div><strong>Tipo:</strong> {{ $ocorrencia->tipoLabel() }}</div>
                        <div><strong>Data:</strong> {{ $ocorrencia->ocorrido_em?->format('d/m/Y') ?? '—' }}</div>
                        @if (filled($ocorrencia->responsavel))
                            <div><strong>Responsável:</strong> {{ $ocorrencia->responsavel }}</div>
                        @endif
                        @if ($ocorrencia->acesso_restrito)
                            <div><strong>Acesso:</strong> Restrito</div>
                        @endif
                    </div>
                    <p class="desc">{{ $descricaoExibicao }}</p>
                </div>

                <div class="note">
                    Documento gerado em {{ $printedAt->format('d/m/Y H:i') }}. Este registro não aplica penalidades automáticas à folha.
                </div>
            </div>
        </div>
    </div>

    @if ($autoPrint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
