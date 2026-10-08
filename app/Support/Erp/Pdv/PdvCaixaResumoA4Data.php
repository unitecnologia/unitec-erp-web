<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Support\Erp\Compra\CompraDanfeReportService;
use App\Support\Erp\ErpTimezone;

/**
 * Dados do RESUMO CAIXA em A4 (somente leitura: não fecha caixa nem altera saldos).
 * Mesmas fontes do resumo térmico (PdvCaixaResumoMovimentos + movimentos da sessão).
 */
final class PdvCaixaResumoA4Data
{
    /**
     * @return array<string, mixed>
     */
    public function build(
        PdvCaixaSessao $sessao,
        ?Empresa $empresa,
        ?float $dinheiroInformado,
        ?string $usuarioFallback = null,
    ): array {
        $sessao->loadMissing(['user.vendedor', 'terminal', 'movimentos']);

        $linhas = PdvCaixaResumoMovimentos::fromSessao($sessao);

        $totalEntrada = 0.0;
        $totalSaida = 0.0;
        $abertura = 0.0;
        $formas = [];
        $totalSangria = 0.0;
        $totalSuprimento = 0.0;

        foreach ($linhas as $linha) {
            $entrada = round((float) ($linha['entrada'] ?? 0), 2);
            $saida = round((float) ($linha['saida'] ?? 0), 2);
            $totalEntrada = round($totalEntrada + $entrada, 2);
            $totalSaida = round($totalSaida + $saida, 2);

            match ($linha['grupo'] ?? 'forma') {
                'abertura' => $abertura = round($abertura + $entrada - $saida, 2),
                'sangria' => $totalSangria = round($totalSangria + $saida - $entrada, 2),
                'suprimento' => $totalSuprimento = round($totalSuprimento + $entrada - $saida, 2),
                default => $formas[] = [
                    'forma' => (string) $linha['historico'],
                    'entrada' => $entrada,
                    'saida' => $saida,
                    'saldo' => round($entrada - $saida, 2),
                ],
            };
        }

        $operacoes = $sessao->movimentos
            ->filter(fn (PdvCaixaMovimento $m): bool => in_array(
                mb_strtolower(trim((string) $m->tipo), 'UTF-8'),
                ['sangria', 'suprimento'],
                true,
            ))
            ->sortBy('id')
            ->map(fn (PdvCaixaMovimento $m): array => [
                'tipo' => mb_strtolower(trim((string) $m->tipo), 'UTF-8'),
                'hora' => $this->formatar($m->created_at, 'd/m H:i'),
                'historico' => mb_strtoupper(trim((string) ($m->historico ?: '—')), 'UTF-8'),
                'forma' => mb_strtoupper(trim((string) ($m->forma_pagamento ?: 'DINHEIRO')), 'UTF-8'),
                'valor' => round(max((float) $m->entrada, (float) $m->saida), 2),
            ])
            ->values();

        $vendasCanceladas = PdvVenda::query()
            ->with('nfce:id,pdv_venda_id,numero,serie,simulada')
            ->where('pdv_caixa_sessao_id', $sessao->id)
            ->where('situacao', 'C')
            ->orderBy('id')
            ->get(['id', 'numero', 'total', 'motivo_estorno', 'fechado_em', 'updated_at'])
            ->map(fn (PdvVenda $v): array => [
                'numero' => (string) $v->numero,
                'nfce' => $v->nfce && ! $v->nfce->simulada && $v->nfce->numero
                    ? $v->nfce->numero.' / '.($v->nfce->serie ?: '1')
                    : '—',
                'em' => $this->formatar($v->updated_at ?? $v->fechado_em, 'd/m/Y H:i'),
                'motivo' => trim((string) ($v->motivo_estorno ?: '—')),
                'total' => round((float) $v->total, 2),
            ])
            ->all();

        $produtosCancelados = [];
        foreach (is_array($sessao->itens_cancelados) ? $sessao->itens_cancelados : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $produtosCancelados[] = [
                'codigo' => trim((string) ($row['codigo'] ?? '')),
                'descricao' => mb_strtoupper(trim((string) ($row['descricao'] ?? '')), 'UTF-8'),
                'qtd' => (float) ($row['qtd'] ?? 0),
                'total' => round((float) ($row['total'] ?? 0), 2),
                'em' => $this->formatar($row['em'] ?? null, 'd/m H:i'),
            ];
        }

        $caixaAberto = $sessao->fechado_em === null;
        $saldoDinheiro = (float) $sessao->saldoDinheiro();
        $informado = $dinheiroInformado !== null && $dinheiroInformado > 0 ? round($dinheiroInformado, 2) : null;

        $user = $sessao->user;
        $usuario = mb_strtoupper(trim((string) ($user?->name ?? $usuarioFallback ?? '—')), 'UTF-8');
        $operador = mb_strtoupper(trim((string) ($user?->vendedor?->nome ?? '')), 'UTF-8');

        $danfe = new CompraDanfeReportService();
        $emitente = app(PdvNfceCancelamentoProtocoloService::class)->buildEmitente($empresa);
        $emitente['telefone'] = (string) ($empresa?->telefone ?? '');

        return [
            'emitente' => $emitente,
            'logoDataUri' => $danfe->logoDataUri($empresa),
            'sessaoId' => (int) $sessao->id,
            'usuario' => $usuario,
            'operador' => $operador !== '' ? $operador : $usuario,
            'terminal' => mb_strtoupper(trim((string) ($sessao->terminal?->nome ?? 'PDV')), 'UTF-8'),
            'caixaAberto' => $caixaAberto,
            'abertoEm' => $this->formatar($sessao->aberto_em, 'd/m/Y H:i:s'),
            'fechadoEm' => $caixaAberto ? null : $this->formatar($sessao->fechado_em, 'd/m/Y H:i:s'),
            'impressoEm' => ErpTimezone::toLocal()->format('d/m/Y H:i:s'),
            'abertura' => $abertura,
            'formas' => $formas,
            'operacoes' => $operacoes->all(),
            'totalSangria' => $totalSangria,
            'totalSuprimento' => $totalSuprimento,
            'vendasCanceladas' => $vendasCanceladas,
            'totalVendasCanceladas' => round(array_sum(array_column($vendasCanceladas, 'total')), 2),
            'produtosCancelados' => $produtosCancelados,
            'totalProdutosCancelados' => round(array_sum(array_column($produtosCancelados, 'total')), 2),
            'totalEntrada' => $totalEntrada,
            'totalSaida' => $totalSaida,
            'saldoTotal' => round($totalEntrada - $totalSaida, 2),
            'saldoDinheiro' => $saldoDinheiro,
            'dinheiroInformado' => $informado,
            'diferencaDinheiro' => $informado !== null ? round($informado - $saldoDinheiro, 2) : null,
        ];
    }

    private function formatar(mixed $valor, string $formato): string
    {
        if (blank($valor)) {
            return '—';
        }

        try {
            return ErpTimezone::toLocal($valor)->format($formato);
        } catch (\Throwable) {
            return '—';
        }
    }
}
