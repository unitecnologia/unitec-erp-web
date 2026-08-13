<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Mercado Livre' }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f1f5f9; margin: 0; min-height: 100vh; display: grid; place-items: center; }
        .card { background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 1.5rem 1.75rem; max-width: 28rem; width: calc(100% - 2rem); box-shadow: 0 8px 24px rgba(15, 23, 42, .08); }
        h1 { margin: 0 0 .75rem; font-size: 1.15rem; color: #0f172a; }
        p { margin: 0; color: #334155; line-height: 1.45; font-size: .95rem; }
        .ok { color: #166534; }
        .err { color: #991b1b; }
        .meta { margin-top: .85rem; font-size: .85rem; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <h1 class="{{ !empty($ok) ? 'ok' : 'err' }}">{{ $title ?? 'Mercado Livre' }}</h1>
        <p>{{ $message ?? '' }}</p>
        @if (!empty($nickname))
            <p class="meta">Conta: <strong>{{ $nickname }}</strong></p>
        @endif
        @if (!empty($ok))
            <p class="meta">Pode fechar esta janela e voltar ao ERP.</p>
        @endif
    </div>
</body>
</html>
