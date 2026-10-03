@php
    use App\Support\Erp\Orcamento\OrcamentoReportService;

    $report = app(OrcamentoReportService::class);
    $totais = $totais ?? $report->totaisImpressao($orcamento);
    $clienteNome = $orcamento->clienteDisplayNome() ?: '—';
    $fantasia = mb_strtoupper(trim((string) ($orcamento->cliente?->apelido_fantasia ?? '')), 'UTF-8');
    $endereco = trim(implode(', ', array_filter([
        $orcamento->clienteDisplayEndereco(),
        $orcamento->clienteDisplayNumero(),
    ])));
    $documento = trim($orcamento->clienteDisplayCpfCnpj());
    $ie = trim((string) ($orcamento->cliente?->rg_ie ?? ''));
    $fone = trim($orcamento->clienteDisplayFone() ?: $orcamento->clienteDisplayWhatsapp());
    $vendedor = mb_strtoupper(trim((string) ($orcamento->vendedor?->nome ?? '')), 'UTF-8');
    $formaPagamento = mb_strtoupper(trim((string) ($orcamento->forma_pagamento ?? '')), 'UTF-8');
@endphp

<div class="monitor-pedidos-doc orc-doc">
    <table class="orc-doc__empresa">
        <tr>
            <td class="orc-doc__logo">
                @if (filled($logoDataUri ?? null))
                    <img src="{{ $logoDataUri }}" alt="Logomarca">
                @elseif (filled($logoUrl ?? null))
                    <img src="{{ $logoUrl }}" alt="Logomarca">
                @endif
            </td>
            <td>
                <span class="orc-doc__empresa-nome">{{ mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8') }}</span>
                @if (filled($empresa?->responsavel))
                    <span class="orc-doc__empresa-linha">{{ mb_strtoupper($empresa->responsavel, 'UTF-8') }}</span>
                @endif
                @if (filled($empresaEndereco ?? null))
                    <span class="orc-doc__empresa-linha">{{ $empresaEndereco }}</span>
                @endif
                @if (filled($empresaCidadeUf ?? null))
                    <span class="orc-doc__empresa-linha">{{ $empresaCidadeUf }}</span>
                @endif
                @if (filled($empresa?->cnpj))
                    <span class="orc-doc__empresa-linha">CNPJ: {{ $empresa->cnpj }}</span>
                @endif
                <span class="orc-doc__empresa-linha">FONE: {{ $empresa?->telefone ?: '' }}&nbsp;&nbsp;EMAIL: {{ $empresa?->email ?: '' }}</span>
            </td>
        </tr>
    </table>

    <table class="monitor-pedidos-doc__sheet-header">
        <tr>
            <td>ORÇAMENTO Nº {{ $numero }}</td>
        </tr>
    </table>

    <div class="monitor-pedidos-doc__meta-wrap">
        @if (filled($statusLabel ?? null))
            <span class="monitor-pedidos-doc__status monitor-pedidos-doc__status--{{ $statusKey ?? 'pendente' }}">
                {{ $statusLabel }}
            </span>
        @endif
        <table class="monitor-pedidos-doc__meta">
            <tr>
                <td>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">Cliente:</span>
                        {{ $clienteNome }}
                    </div>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">Fantasia:</span>
                        {{ $fantasia }}
                    </div>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">Endereço:</span>
                        {{ $endereco }}
                    </div>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">Município:</span>
                        {{ $orcamento->clienteDisplayCidade() }}
                    </div>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">Vendedor:</span>
                        {{ $vendedor }}
                    </div>
                </td>
                <td>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">CNPJ:</span>
                        {{ $documento }}
                        &nbsp;<span class="monitor-pedidos-doc__meta-label">IE:</span>
                        {{ $ie }}
                    </div>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">Data:</span>
                        {{ $orcamento->data?->format('d/m/Y') ?? '' }}
                    </div>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">Validade:</span>
                        {{ (int) ($orcamento->validade_dias ?? 0) }} dias
                    </div>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">Forma de Pagamento:</span>
                        {{ $formaPagamento }}
                    </div>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">Bairro:</span>
                        {{ $orcamento->clienteDisplayBairro() }}
                    </div>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">CEP:</span>
                        {{ $orcamento->clienteDisplayCep() }}
                        &nbsp;&nbsp;
                        <span class="monitor-pedidos-doc__meta-label">UF:</span>
                        {{ $orcamento->clienteDisplayUf() }}
                    </div>
                    <div class="monitor-pedidos-doc__meta-line">
                        <span class="monitor-pedidos-doc__meta-label">Fone:</span>
                        {{ $fone }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="monitor-pedidos-doc__table monitor-pedidos-doc__table--com-desconto">
        @include('reports.partials.monitor-pedidos-document-colgroup', ['impSemColunaDesconto' => false])
        <thead>
            <tr class="monitor-pedidos-doc__table-head">
                <th class="col-codigo">Código</th>
                <th class="col-produto">Produto</th>
                <th class="col-un">UN</th>
                <th class="col-qtd">Qtd</th>
                <th class="col-unit">Valor Unit.</th>
                <th class="col-desc">Desconto</th>
                <th class="col-sub">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orcamento->itens as $item)
                @php
                    $linha = $report->linhaImpressao($item);
                @endphp
                <tr>
                    <td class="col-codigo">{{ $linha['codigo'] }}</td>
                    <td class="produto">{{ mb_strtoupper($linha['produto'], 'UTF-8') }}</td>
                    <td class="col-un">{{ $linha['unidade'] }}</td>
                    <td class="col-qtd">{{ OrcamentoReportService::formatQuantidade($linha['quantidade']) }}</td>
                    <td class="col-unit">{{ OrcamentoReportService::formatMoney($linha['valor_unitario']) }}</td>
                    <td class="col-desc">{{ OrcamentoReportService::formatMoney($linha['desconto']) }}</td>
                    <td class="col-sub">{{ OrcamentoReportService::formatMoney($linha['subtotal']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Nenhum item informado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="orc-doc__fechamento">
        <tr>
            <td class="label">Subtotal bruto</td>
            <td class="valor">{{ OrcamentoReportService::formatMoney((float) $totais['subtotal_bruto']) }}</td>
        </tr>
        <tr>
            <td class="label">Descontos</td>
            <td class="valor">{{ OrcamentoReportService::formatMoney((float) $totais['descontos']) }}</td>
        </tr>
        <tr class="total">
            <td class="label">Total</td>
            <td class="valor">{{ OrcamentoReportService::formatMoney((float) $totais['total']) }}</td>
        </tr>
    </table>

    <div class="orc-doc__obs">
        <div class="orc-doc__obs-title">Observações</div>
        <div class="orc-doc__obs-text">{{ $orcamento->observacoes ?: '' }}</div>
    </div>

    <div class="monitor-pedidos-doc__rodape">
        <span class="monitor-pedidos-doc__impresso">
            IMPRESSO POR {{ mb_strtoupper((string) ($printedBy ?? '—'), 'UTF-8') }}
            — {{ ($printedAt ?? now())->format('d/m/Y H:i:s') }}
        </span>
        <span class="monitor-pedidos-doc__credito">
            Desenvolvido Por Unitecnologia Sistemas LTDA
        </span>
    </div>
</div>
