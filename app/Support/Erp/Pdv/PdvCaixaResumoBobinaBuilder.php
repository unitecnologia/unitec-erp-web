<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Orcamento\OrcamentoBobinaFormatter as F;
use Illuminate\Support\Collection;

/**
 * Linhas monoespaçadas (~48 cols) do RESUMO CAIXA.
 * Usado pelo HTML bobina e pelo ESC/POS (Device Service).
 */
final class PdvCaixaResumoBobinaBuilder
{
    /**
     * @param  list<array{historico: string, entrada: float, saida: float}>  $movimentos
     * @param  Collection<int, PdvVenda>|iterable<int, PdvVenda>  $vendasCanceladas
     * @param  list<array<string, mixed>>  $produtosCancelados
     * @return list<string>
     */
    public function buildLines(
        ?Empresa $empresa,
        string $dataHora,
        string $usuario,
        string $caixaLabel,
        bool $caixaAberto,
        string $abertoEm,
        ?string $fechadoEm,
        array $movimentos,
        iterable $vendasCanceladas,
        array $produtosCancelados,
        float $totalEntrada,
        float $totalSaida,
        float $saldoTotal,
        float $saldoDinheiro,
        float $dinheiroInformado,
        float $diferencaDinheiro,
    ): array {
        $lines = [];

        $fantasia = mb_strtoupper((string) ($empresa?->fantasia ?: $empresa?->nome ?? 'EMPRESA'), 'UTF-8');
        foreach (F::wrap($fantasia) as $line) {
            $lines[] = F::center($line);
        }

        if (filled($empresa?->razao_social ?? null) && ($empresa->razao_social !== ($empresa->nome ?? ''))) {
            foreach (F::wrap(mb_strtoupper((string) $empresa->razao_social, 'UTF-8')) as $line) {
                $lines[] = F::center($line);
            }
        }

        $cnpjIe = 'CNPJ: '.($empresa->cnpj ?? '—');
        if (filled($empresa->ie ?? null)) {
            $cnpjIe .= ' IE: '.$empresa->ie;
        }
        foreach (F::wrap($cnpjIe) as $line) {
            $lines[] = F::center($line);
        }

        $endereco = trim(implode(', ', array_filter([
            $empresa->endereco ?? null,
            $empresa->bairro ?? null,
            trim(($empresa->cidade ?? '').(($empresa->uf ?? '') !== '' ? ' - '.$empresa->uf : '')),
        ]))) ?: '—';
        foreach (F::wrap(mb_strtoupper($endereco, 'UTF-8')) as $line) {
            $lines[] = F::center($line);
        }

        if (filled($empresa->telefone ?? null)) {
            $lines[] = F::center('Fone: '.$empresa->telefone);
        }

        $lines[] = '';
        $lines[] = F::center('RESUMO CAIXA');
        $lines[] = F::center($caixaAberto ? '*** CAIXA ABERTO ***' : '*** CAIXA FECHADO ***');
        $lines[] = F::center('IMPRESSO: '.$dataHora);
        $lines[] = F::center('USUARIO: '.$usuario);
        $lines[] = F::center('CAIXA: '.$caixaLabel);
        $lines[] = F::center('ABERTURA: '.($abertoEm !== '' ? $abertoEm : '—'));
        if ($caixaAberto) {
            $lines[] = F::center('FECHAMENTO: (ainda aberto)');
        } else {
            $lines[] = F::center('FECHAMENTO: '.($fechadoEm !== null && $fechadoEm !== '' ? $fechadoEm : '—'));
        }
        $lines[] = F::rule('-');
        $lines[] = F::line('FORMA', 'ENTRADA  SAIDA');
        $lines[] = F::rule('-');

        if (count($movimentos) === 0) {
            $lines[] = 'Nenhum movimento.';
        } else {
            foreach ($movimentos as $m) {
                $hist = mb_strtoupper(trim((string) ($m['historico'] ?? '')), 'UTF-8');
                $ent = ErpMoney::formatBr($m['entrada'] ?? 0);
                $sai = ErpMoney::formatBr($m['saida'] ?? 0);
                $right = F::padLeft($ent, 10).' '.F::padLeft($sai, 8);
                $leftMax = F::COLS - mb_strlen($right, 'UTF-8') - 1;
                if (mb_strlen($hist, 'UTF-8') > $leftMax) {
                    foreach (F::wrap($hist) as $i => $wrapLine) {
                        if ($i === 0) {
                            $lines[] = F::line(mb_substr($wrapLine, 0, $leftMax, 'UTF-8'), $right);
                        } else {
                            $lines[] = $wrapLine;
                        }
                    }
                } else {
                    $lines[] = F::line($hist !== '' ? $hist : '—', $right);
                }
            }
        }

        $lines[] = '';
        $lines[] = F::center('VENDAS CANCELADAS');
        $lines[] = F::rule('-');

        $vendas = $vendasCanceladas instanceof Collection
            ? $vendasCanceladas
            : collect($vendasCanceladas);

        if ($vendas->isEmpty()) {
            $lines[] = 'Nenhuma venda cancelada.';
        } else {
            foreach ($vendas as $v) {
                $emVenda = '—';
                $momento = $v->updated_at ?? $v->fechado_em;
                if ($momento) {
                    try {
                        $emVenda = ErpTimezone::toLocal($momento)->format('d/m/Y H:i');
                    } catch (\Throwable) {
                        $emVenda = '—';
                    }
                }
                $lines[] = F::line('N:'.((string) $v->numero), ErpMoney::formatBr($v->total));
                $lines[] = $emVenda;
                $motivo = trim((string) ($v->motivo_estorno ?: '—'));
                foreach (F::wrap($motivo) as $line) {
                    $lines[] = $line;
                }
                $lines[] = F::rule('-');
            }
        }

        $lines[] = '';
        $lines[] = F::center('PRODUTOS CANCELADOS');
        $lines[] = F::rule('-');

        if (count($produtosCancelados) === 0) {
            $lines[] = 'Nenhum produto cancelado.';
        } else {
            foreach ($produtosCancelados as $p) {
                $emRaw = $p['em'] ?? null;
                $emFmt = '—';
                if (filled($emRaw)) {
                    try {
                        $emFmt = ErpTimezone::toLocal($emRaw)->format('d/m/Y H:i');
                    } catch (\Throwable) {
                        $emFmt = '—';
                    }
                }
                $cod = trim((string) ($p['codigo'] ?? ''));
                $desc = mb_strtoupper(trim((string) ($p['descricao'] ?? '')), 'UTF-8');
                $qtd = ErpMoney::formatBr((float) ($p['qtd'] ?? 0), 3);
                $tot = ErpMoney::formatBr((float) ($p['total'] ?? 0));
                $texto = trim(($cod !== '' ? $cod.' ' : '').$desc);
                if ($texto === '') {
                    $texto = '—';
                }

                $leftMax = F::COLS - mb_strlen($tot, 'UTF-8') - 1;
                if (mb_strlen($texto, 'UTF-8') <= $leftMax) {
                    $lines[] = F::line($texto, $tot);
                } else {
                    $slice = mb_substr($texto, 0, $leftMax, 'UTF-8');
                    $breakAt = mb_strrpos($slice, ' ', 0, 'UTF-8');
                    if ($breakAt !== false && $breakAt > 8) {
                        $primeira = rtrim(mb_substr($texto, 0, $breakAt, 'UTF-8'));
                        $resto = ltrim(mb_substr($texto, $breakAt, null, 'UTF-8'));
                    } else {
                        $primeira = $slice;
                        $resto = ltrim(mb_substr($texto, $leftMax, null, 'UTF-8'));
                    }
                    $lines[] = F::line($primeira, $tot);
                    foreach (F::wrap($resto) as $line) {
                        $lines[] = $line;
                    }
                }
                $lines[] = $emFmt.' QTD:'.$qtd;
                $lines[] = F::rule('-');
            }
        }

        $lines[] = '';
        $lines[] = F::center('MOVIMENTACAO GERAL CAIXA');
        $lines[] = F::rule('-');
        $lines[] = F::line('TOTAL ENTRADA', ErpMoney::formatBr($totalEntrada));
        $lines[] = F::line('TOTAL SAIDA', ErpMoney::formatBr($totalSaida));
        $lines[] = F::line('SALDO TOTAL', ErpMoney::formatBr($saldoTotal));

        $lines[] = '';
        $lines[] = F::center('MOVIMENTACAO DINHEIRO CAIXA');
        $lines[] = F::rule('-');
        $lines[] = F::line('TOTAL ENTRADA (SISTEMA)', ErpMoney::formatBr($saldoDinheiro));
        $lines[] = F::line('TOTAL INFORMADO', ErpMoney::formatBr($dinheiroInformado));
        $lines[] = F::line('SALDO (INF - SISTEMA)', ErpMoney::formatBr($diferencaDinheiro));

        return $lines;
    }

    /**
     * Monta linhas a partir da sessão (mesma fonte do relatório HTML).
     *
     * @return array{
     *     lines: list<string>,
     *     dinheiroInformado: float,
     *     usuario: string,
     *     caixaLabel: string,
     *     dataHora: string
     * }
     */
    public function buildFromSessao(
        PdvCaixaSessao $sessao,
        ?Empresa $empresa,
        float $dinheiroInformado,
        ?string $usuarioFallback = null,
    ): array {
        $sessao->loadMissing(['user', 'terminal', 'movimentos']);

        $movimentos = PdvCaixaResumoMovimentos::fromSessao($sessao);
        $entrada = 0.0;
        $saida = 0.0;
        foreach ($movimentos as $linha) {
            $entrada = round($entrada + (float) ($linha['entrada'] ?? 0), 2);
            $saida = round($saida + (float) ($linha['saida'] ?? 0), 2);
        }

        $vendasCanceladas = PdvVenda::query()
            ->where('pdv_caixa_sessao_id', $sessao->id)
            ->where('situacao', 'C')
            ->orderBy('id')
            ->get(['numero', 'total', 'motivo_estorno', 'fechado_em', 'updated_at']);

        $produtosCancelados = is_array($sessao->itens_cancelados) ? $sessao->itens_cancelados : [];

        $caixaAberto = $sessao->fechado_em === null;
        $abertoEm = '—';
        if ($sessao->aberto_em) {
            try {
                $abertoEm = ErpTimezone::toLocal($sessao->aberto_em)->format('d/m/Y H:i:s');
            } catch (\Throwable) {
                $abertoEm = '—';
            }
        }

        $fechadoEm = null;
        if (! $caixaAberto && $sessao->fechado_em) {
            try {
                $fechadoEm = ErpTimezone::toLocal($sessao->fechado_em)->format('d/m/Y H:i:s');
            } catch (\Throwable) {
                $fechadoEm = '—';
            }
        }

        $agora = $caixaAberto
            ? ErpTimezone::toLocal()
            : ErpTimezone::toLocal($sessao->fechado_em);

        $entradaDinheiro = (float) $sessao->saldoDinheiro();
        $diferenca = round($dinheiroInformado - $entradaDinheiro, 2);

        $usuario = mb_strtoupper(trim((string) ($sessao->user?->name ?? $usuarioFallback ?? 'USUARIO')), 'UTF-8');
        $caixaLabel = (string) ($sessao->terminal?->nome ?? $sessao->id);
        $dataHora = $agora->format('d/m/Y H:i:s');

        return [
            'lines' => $this->buildLines(
                $empresa,
                $dataHora,
                $usuario,
                $caixaLabel,
                $caixaAberto,
                $abertoEm,
                $fechadoEm,
                $movimentos,
                $vendasCanceladas,
                $produtosCancelados,
                $entrada,
                $saida,
                round($entrada - $saida, 2),
                $entradaDinheiro,
                $dinheiroInformado,
                $diferenca,
            ),
            'dinheiroInformado' => $dinheiroInformado,
            'usuario' => $usuario,
            'caixaLabel' => $caixaLabel,
            'dataHora' => $dataHora,
        ];
    }
}
