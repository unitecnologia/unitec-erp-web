<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\CaixaLivroMovimento;
use App\Models\Empresa;
use App\Support\Erp\CaixaHistoricoExibicao;
use App\Support\Erp\Compra\CompraDanfeReportService;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Pdv\PdvNfceCancelamentoProtocoloService;
use Illuminate\Support\Facades\Schema;

/**
 * Dados do relatório A4 de fechamento do Livro Caixa.
 * Lê o movimento persistido — não recalcula nem altera histórico.
 * Detalhes pesados só quando solicitados pelas flags.
 */
final class CaixaLivroFechamentoReportData
{
    /**
     * @param  array{
     *     resumo?: bool,
     *     formas?: bool,
     *     vendas?: bool,
     *     clientes?: bool,
     *     entradas_saidas?: bool,
     *     transferencias?: bool,
     *     operadores?: bool
     * }  $flags
     * @return array<string, mixed>
     */
    public function build(CaixaLivroMovimento $mov, array $flags = []): array
    {
        $mov->loadMissing([
            'empresa',
            'conta',
            'abertoPor:id,name',
            'fechadoPor:id,name',
        ]);

        if (! $mov->isFechado()) {
            throw new \InvalidArgumentException(
                'Só é possível imprimir fechamento de movimento já fechado.'
            );
        }

        $empresa = $mov->empresa instanceof Empresa ? $mov->empresa : null;
        $conta = $mov->conta instanceof CaixaConta ? $mov->conta : null;

        $emitente = app(PdvNfceCancelamentoProtocoloService::class)->buildEmitente($empresa);
        $emitente['telefone'] = (string) ($empresa?->telefone ?? '');
        $logoDataUri = (new CompraDanfeReportService())->logoDataUri($empresa);

        $conferencia = is_array($mov->conferencia_formas) ? $mov->conferencia_formas : [];
        $linhasFormas = is_array($conferencia['linhas'] ?? null) ? $conferencia['linhas'] : [];
        $totaisConf = is_array($conferencia['totais'] ?? null) ? $conferencia['totais'] : [];

        $flags = $this->normalizeFlags($flags);

        $detalheEntradasSaidas = [];
        $detalheTransferencias = [];
        $detalheVendas = [];
        $detalheClientes = [];
        $avisoVendas = null;
        $avisoClientes = null;

        $precisaLancamentos = $flags['entradas_saidas']
            || $flags['transferencias']
            || $flags['vendas']
            || $flags['clientes'];

        if ($precisaLancamentos && $conta) {
            $lancamentos = $this->lancamentosDoPeriodo($mov);

            if ($flags['entradas_saidas']) {
                $detalheEntradasSaidas = $this->mapearOperacionais($lancamentos);
            }
            if ($flags['transferencias']) {
                $detalheTransferencias = $this->mapearTransferencias($lancamentos);
            }
            if ($flags['vendas'] || $flags['clientes']) {
                $vinculos = $this->mapearVinculosVendaCliente($lancamentos);
                if ($flags['vendas']) {
                    $detalheVendas = $vinculos['vendas'];
                    if ($detalheVendas === []) {
                        $avisoVendas = 'Não há vínculo comprovado entre os lançamentos deste movimento e vendas/recebimentos para detalhar.';
                    }
                }
                if ($flags['clientes']) {
                    $detalheClientes = $vinculos['clientes'];
                    if ($detalheClientes === []) {
                        $avisoClientes = 'Não há clientes vinculados de forma comprovada aos lançamentos deste movimento.';
                    }
                }
            }
        } elseif ($flags['vendas']) {
            $avisoVendas = 'Não há vínculo comprovado entre este movimento do Livro Caixa e vendas para detalhar.';
        } elseif ($flags['clientes']) {
            $avisoClientes = 'Não há clientes vinculados de forma comprovada a este movimento.';
        }

        return [
            'movimentoId' => (int) $mov->id,
            'status' => 'FECHADO',
            'emitente' => $emitente,
            'logoDataUri' => $logoDataUri,
            'empresaNome' => mb_strtoupper((string) ($empresa?->razao_social ?: $empresa?->nome_fantasia ?: 'EMPRESA'), 'UTF-8'),
            'contaNome' => mb_strtoupper((string) ($conta?->nome ?? '—'), 'UTF-8'),
            'abertoEm' => optional($mov->aberto_em)?->format('d/m/Y H:i') ?? '—',
            'fechadoEm' => optional($mov->fechado_em)?->format('d/m/Y H:i') ?? '—',
            'abertoPor' => mb_strtoupper((string) ($mov->abertoPor?->name ?? '—'), 'UTF-8'),
            'fechadoPor' => mb_strtoupper((string) ($mov->fechadoPor?->name ?? '—'), 'UTF-8'),
            'trocoInicial' => round((float) $mov->troco_inicial, 2),
            'saldoAberturaFin' => round((float) $mov->saldo_abertura, 2),
            'totalEntradas' => round((float) ($mov->total_entradas ?? 0), 2),
            'totalSaidas' => round((float) ($mov->total_saidas ?? 0), 2),
            'trfEntrada' => round((float) ($mov->total_transferencias_entrada ?? 0), 2),
            'trfSaida' => round((float) ($mov->total_transferencias_saida ?? 0), 2),
            'saldoFinanceiroAtual' => round(
                (float) $mov->saldo_abertura
                + (float) ($mov->total_entradas ?? 0)
                - (float) ($mov->total_saidas ?? 0),
                2
            ),
            'saldoMovimento' => round((float) ($mov->saldo_calculado ?? 0), 2),
            'valorContado' => round((float) ($mov->valor_contado ?? 0), 2),
            'totalInformado' => round((float) ($mov->total_informado ?? $totaisConf['informado'] ?? 0), 2),
            'diferenca' => $mov->diferenca !== null ? round((float) $mov->diferenca, 2) : null,
            'observacao' => (string) ($mov->observacao_fechamento ?? ''),
            'formas' => $linhasFormas,
            'totaisFormas' => [
                'informado' => round((float) ($totaisConf['informado'] ?? $mov->total_informado ?? 0), 2),
                'fisico' => round((float) ($totaisConf['fisico'] ?? 0), 2),
                'eletronico' => round((float) ($totaisConf['eletronico'] ?? 0), 2),
                'outros' => round((float) ($totaisConf['outros'] ?? 0), 2),
            ],
            'flags' => $flags,
            'detalheEntradasSaidas' => $detalheEntradasSaidas,
            'detalheTransferencias' => $detalheTransferencias,
            'detalheVendas' => $detalheVendas,
            'detalheClientes' => $detalheClientes,
            'avisoVendas' => $avisoVendas,
            'avisoClientes' => $avisoClientes,
            'impressoEm' => ErpTimezone::toLocal()->format('d/m/Y H:i'),
        ];
    }

    /**
     * @param  array<string, mixed>  $flags
     * @return array{
     *     resumo: bool,
     *     formas: bool,
     *     vendas: bool,
     *     clientes: bool,
     *     entradas_saidas: bool,
     *     transferencias: bool,
     *     operadores: bool
     * }
     */
    public function normalizeFlags(array $flags): array
    {
        return [
            'resumo' => (bool) ($flags['resumo'] ?? true),
            'formas' => (bool) ($flags['formas'] ?? true),
            'vendas' => (bool) ($flags['vendas'] ?? false),
            'clientes' => (bool) ($flags['clientes'] ?? false),
            'entradas_saidas' => (bool) ($flags['entradas_saidas'] ?? false),
            'transferencias' => (bool) ($flags['transferencias'] ?? false),
            'operadores' => (bool) ($flags['operadores'] ?? true),
        ];
    }

    /**
     * @return array{
     *     resumo: bool,
     *     formas: bool,
     *     vendas: bool,
     *     clientes: bool,
     *     entradas_saidas: bool,
     *     transferencias: bool,
     *     operadores: bool
     * }
     */
    public static function flagsResumido(): array
    {
        return [
            'resumo' => true,
            'formas' => true,
            'vendas' => false,
            'clientes' => false,
            'entradas_saidas' => false,
            'transferencias' => false,
            'operadores' => true,
        ];
    }

    /**
     * @return array{
     *     resumo: bool,
     *     formas: bool,
     *     vendas: bool,
     *     clientes: bool,
     *     entradas_saidas: bool,
     *     transferencias: bool,
     *     operadores: bool
     * }
     */
    public static function flagsCompleto(): array
    {
        return [
            'resumo' => true,
            'formas' => true,
            'vendas' => true,
            'clientes' => true,
            'entradas_saidas' => true,
            'transferencias' => true,
            'operadores' => true,
        ];
    }

    /**
     * @return list<CaixaLancamento>
     */
    private function lancamentosDoPeriodo(CaixaLivroMovimento $mov): array
    {
        if (! Schema::hasTable('caixa_lancamentos')) {
            return [];
        }

        return app(CaixaLivroMovimentoService::class)
            ->queryLancamentosDoMovimento($mov)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @param  list<CaixaLancamento>  $lancamentos
     * @return list<array<string, mixed>>
     */
    private function mapearOperacionais(array $lancamentos): array
    {
        $out = [];
        foreach ($lancamentos as $l) {
            $doc = trim((string) ($l->documento ?? ''));
            if (CaixaTransferencia::isDocumentoTransferencia($doc)) {
                continue;
            }
            $out[] = [
                'emissao' => optional($l->emissao)?->format('d/m/Y') ?? '—',
                'historico' => CaixaHistoricoExibicao::paraExibicao((string) ($l->historico ?: '—')) ?: '—',
                'documento' => mb_strtoupper($doc !== '' ? $doc : '—', 'UTF-8'),
                'entrada' => round((float) $l->entrada, 2),
                'saida' => round((float) $l->saida, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  list<CaixaLancamento>  $lancamentos
     * @return list<array<string, mixed>>
     */
    private function mapearTransferencias(array $lancamentos): array
    {
        $out = [];
        foreach ($lancamentos as $l) {
            $doc = trim((string) ($l->documento ?? ''));
            if (! CaixaTransferencia::isDocumentoTransferencia($doc)) {
                continue;
            }
            $out[] = [
                'emissao' => optional($l->emissao)?->format('d/m/Y') ?? '—',
                'historico' => CaixaHistoricoExibicao::paraExibicao((string) ($l->historico ?: 'TRANSFERÊNCIA')) ?: 'TRANSFERÊNCIA',
                'documento' => mb_strtoupper($doc, 'UTF-8'),
                'entrada' => round((float) $l->entrada, 2),
                'saida' => round((float) $l->saida, 2),
            ];
        }

        return $out;
    }

    /**
     * Vínculos reais via histórico/documento (sem inventar PDV/DAV).
     *
     * @param  list<CaixaLancamento>  $lancamentos
     * @return array{vendas: list<array<string, mixed>>, clientes: list<array<string, mixed>>}
     */
    private function mapearVinculosVendaCliente(array $lancamentos): array
    {
        $resolver = app(CaixaLancamentoOrigemResolver::class);
        $vendas = [];
        $clientes = [];
        $clientesSeen = [];

        foreach ($lancamentos as $l) {
            $doc = trim((string) ($l->documento ?? ''));
            if (CaixaTransferencia::isDocumentoTransferencia($doc)) {
                continue;
            }

            $detalhe = $resolver->resolver($l);
            if (! ($detalhe['tem_detalhe'] ?? false)) {
                continue;
            }

            $mapa = [];
            foreach ($detalhe['linhas'] as $linha) {
                $mapa[(string) ($linha['label'] ?? '')] = (string) ($linha['value'] ?? '');
            }

            $origem = $mapa['Origem'] ?? '';
            $cliente = $mapa['Cliente'] ?? ($mapa['Pessoa'] ?? '');
            $docRef = $mapa['Documento'] ?? ($mapa['Título'] ?? $doc);
            $valor = round(max((float) $l->entrada, (float) $l->saida), 2);

            $ehVenda = (bool) preg_match(
                '/receb|venda|pdv|nfce|nfe|for[cç]a\s*vendas|os\b|dav/iu',
                $origem.' '.$doc.' '.(string) ($l->historico ?? '')
            );

            if ($ehVenda) {
                $vendas[] = [
                    'emissao' => optional($l->emissao)?->format('d/m/Y') ?? '—',
                    'origem' => mb_strtoupper($origem !== '' ? $origem : 'LANÇAMENTO', 'UTF-8'),
                    'documento' => mb_strtoupper($docRef !== '' ? $docRef : '—', 'UTF-8'),
                    'cliente' => mb_strtoupper($cliente !== '' ? $cliente : '—', 'UTF-8'),
                    'valor' => $valor,
                ];
            }

            if ($cliente !== '' && $cliente !== '—') {
                $key = mb_strtoupper($cliente, 'UTF-8');
                if (! isset($clientesSeen[$key])) {
                    $clientesSeen[$key] = true;
                    $clientes[] = [
                        'cliente' => $key,
                        'origem' => mb_strtoupper($origem !== '' ? $origem : '—', 'UTF-8'),
                        'valor' => $valor,
                    ];
                } else {
                    foreach ($clientes as &$c) {
                        if ($c['cliente'] === $key) {
                            $c['valor'] = round((float) $c['valor'] + $valor, 2);
                            break;
                        }
                    }
                    unset($c);
                }
            }
        }

        return ['vendas' => $vendas, 'clientes' => $clientes];
    }
}
