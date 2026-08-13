<div class="pdv-pedido-a4">
    <div class="pdv-pedido-a4__frame">
        <div class="pdv-pedido-a4__header">
            <div class="pdv-pedido-a4__logo-cell">
                <div class="pdv-pedido-a4__logo">
                    @if (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @elseif (filled($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @else
                        <span class="pdv-pedido-a4__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="pdv-pedido-a4__company-cell">
                <span class="pdv-pedido-a4__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
                @if (filled($empresa?->responsavel))
                    <span>{{ mb_strtoupper($empresa->responsavel, 'UTF-8') }}<br></span>
                @endif
                @if (filled($empresaEndereco))
                    <span>{{ $empresaEndereco }}<br></span>
                @endif
                @if (filled($empresaCidadeUf))
                    <span>{{ $empresaCidadeUf }}<br></span>
                @endif
                <span>
                    FONE: {{ $empresa?->telefone ?: '' }}&nbsp;&nbsp;EMAIL: {{ $empresa?->email ?: '' }}
                </span>
            </div>
        </div>

        <hr class="pdv-pedido-a4__rule">

        <div class="pdv-pedido-a4__meta">
            <div class="pdv-pedido-a4__meta-row pdv-pedido-a4__meta-row--split">
                <span><strong>PEDIDO:</strong> {{ $numero }}</span>
                <span><strong>DATA:</strong> {{ $dataVenda }}</span>
            </div>
            <div class="pdv-pedido-a4__meta-row">
                <span><strong>CLIENTE:</strong> {{ $clienteNome }}</span>
            </div>
            <div class="pdv-pedido-a4__meta-row">
                <span><strong>VENDEDOR:</strong> {{ $vendedorNome }}</span>
            </div>
            <div class="pdv-pedido-a4__meta-row">
                <span><strong>MEIO DE PAGAMENTO:</strong> {{ $meioPagamento }}</span>
            </div>
        </div>

        <hr class="pdv-pedido-a4__rule">

        <table class="pdv-pedido-a4__table">
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
                @forelse ($venda->itens as $index => $item)
                    @php
                        $quantidade = (float) $item->quantidade;
                        $quantidadeLabel = fmod($quantidade, 1.0) === 0.0
                            ? (string) (int) $quantidade
                            : number_format($quantidade, 3, ',', '');
                        $unidade = mb_strtoupper($item->unidade ?? $item->product?->unidade ?? 'UN', 'UTF-8');
                    @endphp
                    <tr>
                        <td class="center">{{ $index + 1 }}</td>
                        <td class="produto">{{ mb_strtoupper($item->descricao, 'UTF-8') }}</td>
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

        <hr class="pdv-pedido-a4__rule">

        <div class="pdv-pedido-a4__totals">
            <span>SubTotal>>> {{ number_format((float) $venda->subtotal, 2, ',', '.') }}</span>
            <span>Desconto>>> {{ number_format((float) $venda->desconto, 2, ',', '.') }}</span>
            <span>Total>>> {{ number_format((float) $venda->total, 2, ',', '.') }}</span>
        </div>

        <hr class="pdv-pedido-a4__rule">

        <div class="pdv-pedido-a4__obs">
            <div class="pdv-pedido-a4__obs-title">Observações:</div>
            <div class="pdv-pedido-a4__obs-text">{{ $venda->observacoes ?: '' }}</div>
        </div>

        <div class="pdv-pedido-a4__footer">
            <div class="pdv-pedido-a4__footer-decl">
                {{ $declaracaoTexto }}
            </div>
            <div class="pdv-pedido-a4__footer-sign">
                <div class="pdv-pedido-a4__sign-line"></div>
                ASSINATURA
            </div>
        </div>
    </div>
</div>
