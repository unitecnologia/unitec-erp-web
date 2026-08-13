@php
    use App\Support\Erp\Reports\ExpedicaoRetiradaReport;

    $cliente = mb_strtoupper($entrega->cliente_nome ?? 'CONSUMIDOR', 'UTF-8');
    $telefone = $entrega->cliente_telefone ?? '—';
@endphp
<style>
    .exp-retirada__assinatura {
        margin-top: 2.5rem;
        display: grid;
        gap: 2rem;
    }
    .exp-retirada__assinatura-linha {
        border-top: 1px solid #111827;
        padding-top: 0.35rem;
        font-size: 0.75rem;
        text-align: center;
        color: #374151;
    }
    .exp-retirada__info {
        margin: 0.5rem 0 1rem;
        font-size: 0.78rem;
        line-height: 1.5;
    }
</style>
<div class="pessoa-list-doc">
    <div class="pessoa-list-doc__frame">
        <div class="pessoa-list-doc__header">
            <div class="pessoa-list-doc__logo-cell">
                <div class="pessoa-list-doc__logo">
                    @if (filled($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="Logomarca">
                    @elseif (filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="Logomarca">
                    @else
                        <span class="pessoa-list-doc__logo-fallback">U</span>
                    @endif
                </div>
            </div>

            <div class="pessoa-list-doc__company-cell">
                <span class="pessoa-list-doc__company-name">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
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

        <hr class="pessoa-list-doc__rule">

        <div class="pessoa-list-doc__title">{{ $reportTitle }}</div>

        <div class="exp-retirada__info">
            <div><strong>Pedido:</strong> {{ $numeroPedido }}</div>
            <div><strong>Cliente:</strong> {{ $cliente }}</div>
            <div><strong>Telefone:</strong> {{ $telefone }}</div>
            <div><strong>Data:</strong> {{ $printedAt->format('d/m/Y H:i') }}</div>
        </div>

        <table class="pessoa-list-doc__table">
            <thead>
                <tr>
                    <th>CÓD.</th>
                    <th>DESCRIÇÃO</th>
                    <th class="num">QTD.</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $linha)
                    <tr>
                        <td>{{ $linha['codigo'] }}</td>
                        <td class="nome">{{ mb_strtoupper($linha['descricao'], 'UTF-8') }}</td>
                        <td class="num">{{ ExpedicaoRetiradaReport::formatQuantidade($linha['quantidade']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="pessoa-list-doc__empty">Nenhum item expedido.</td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($linhas) > 0)
                <tfoot>
                    <tr class="pessoa-list-doc__totals">
                        <td>TOTAIS</td>
                        <td></td>
                        <td class="num">{{ ExpedicaoRetiradaReport::formatQuantidade($totalQuantidade) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="exp-retirada__assinatura">
            <div class="exp-retirada__assinatura-linha">Assinatura do cliente</div>
            <div class="exp-retirada__assinatura-linha">Documento / RG</div>
            <div class="exp-retirada__assinatura-linha">Conferente — expedição</div>
        </div>

        <div class="pessoa-list-doc__footer">
            <span>Relatório emitido em {{ $printedAt->format('d/m/Y - H:i:s') }}</span>
            <span class="pessoa-list-doc__footer-page">Pág. 1</span>
        </div>
    </div>
</div>
