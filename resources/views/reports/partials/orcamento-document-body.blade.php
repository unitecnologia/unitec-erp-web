<div class="orc-doc">
    <div class="orc-doc__frame">
        <div class="orc-doc__header">
            <div class="orc-doc__logo-cell">
                <div class="orc-doc__logo">
                    @if (filled($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="orc-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="orc-doc__company-cell">
                <span class="orc-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
                @if (filled($empresa?->responsavel))
                    <span>{{ mb_strtoupper($empresa->responsavel, 'UTF-8') }}<br></span>
                @endif
                @if (filled($empresaEndereco))
                    <span>{{ $empresaEndereco }}<br></span>
                @endif
                <span>
                    FONE: {{ $empresa?->telefone ?: '' }}&nbsp;&nbsp;EMAIL: {{ $empresa?->email ?: '' }}
                </span>
            </div>
        </div>

        <hr class="orc-doc__rule">

        <div class="orc-doc__title-row">
            <div class="orc-doc__title">ORÇAMENTO nº {{ $numero }}</div>
            <div class="orc-doc__status">{{ $statusLabel }}</div>
        </div>

        <div class="orc-doc__meta">
            <div class="orc-doc__meta-row orc-doc__meta-row--split">
                <span><strong>DATA:</strong> {{ $orcamento->data?->format('d/m/Y') ?? '—' }}</span>
                <span><strong>VALIDADE:</strong> {{ (int) ($orcamento->validade_dias ?? 0) }} dias</span>
            </div>
            <div class="orc-doc__meta-row">
                <span><strong>CLIENTE:</strong> {{ mb_strtoupper($orcamento->cliente?->nome_razao ?? '—', 'UTF-8') }}</span>
            </div>
            <div class="orc-doc__meta-row">
                <span><strong>VENDEDOR:</strong> {{ mb_strtoupper($orcamento->vendedor?->nome ?? '—', 'UTF-8') }}</span>
            </div>
            <div class="orc-doc__meta-row">
                <span><strong>FPG:</strong> {{ mb_strtoupper($orcamento->forma_pagamento ?? '', 'UTF-8') }}</span>
            </div>
        </div>

        <hr class="orc-doc__rule">

        <table class="orc-doc__table">
            <thead>
                <tr>
                    <th class="center">ITEM</th>
                    <th>PRODUTO</th>
                    <th class="num">PREÇO</th>
                    <th class="num">QUANTIDADE</th>
                    <th class="center">UND</th>
                    <th class="num">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orcamento->itens as $item)
                    @php
                        $quantidade = (float) $item->quantidade;
                        $quantidadeLabel = fmod($quantidade, 1.0) === 0.0
                            ? (string) (int) $quantidade
                            : number_format($quantidade, 3, ',', '');
                        $descricao = filled($item->descricao)
                            ? $item->descricao
                            : ($item->product?->descricao ?? '—');
                        $unidade = mb_strtoupper($item->product?->unidade ?? 'UN', 'UTF-8');
                    @endphp
                    <tr>
                        <td class="center">{{ $item->item }}</td>
                        <td class="produto">{{ mb_strtoupper($descricao, 'UTF-8') }}</td>
                        <td class="num">{{ number_format((float) $item->preco_unitario, 2, ',', '.') }}</td>
                        <td class="num">{{ $quantidadeLabel }}</td>
                        <td class="center">{{ $unidade }}</td>
                        <td class="num">{{ number_format((float) $item->total, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Nenhum item informado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <hr class="orc-doc__rule">

        <div class="orc-doc__totals">
            <span>SubTotal>>> {{ number_format((float) $orcamento->subtotal, 2, ',', '.') }}</span>
            <span>Desconto>>> {{ number_format((float) $orcamento->desconto_valor, 2, ',', '.') }}</span>
            <span>Total>>> {{ number_format((float) $orcamento->total, 2, ',', '.') }}</span>
        </div>

        <hr class="orc-doc__rule">

        <div class="orc-doc__obs">
            <div class="orc-doc__obs-title">Observações:</div>
            <div class="orc-doc__obs-text">{{ $orcamento->observacoes ?: '' }}</div>
        </div>
    </div>
</div>
