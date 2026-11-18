<?php

namespace App\Support\Erp\Dre;

use App\Models\DrePlanoClassificacao;
use App\Support\Erp\Financeiro\CaixaTransferencia;
use App\Support\Erp\Tef\TefMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D2.3/D2.4 — Despesas: classificação + competência (títulos CP) + visão caixa informativa.
 *
 * Competência: contas_pagar.competencia (não usa vencimento/pagamento).
 * Inclui títulos em aberto. Não duplica com pagamentos/caixa.
 * Visão caixa D2.3 permanece informativa e não reduz o resultado por competência.
 * Não calcula lucro líquido.
 */
final class DreDespesasGerenciaisService
{
    /**
     * @return array<string, mixed>
     */
    public function calcular(int $empresaId, string $periodoDe, string $periodoAte): array
    {
        $periodoDe = substr(trim($periodoDe), 0, 10);
        $periodoAte = substr(trim($periodoAte), 0, 10);

        if ($empresaId <= 0 || $periodoDe === '' || $periodoAte === '' || $periodoDe > $periodoAte) {
            return $this->vazio($empresaId, $periodoDe, $periodoAte);
        }

        $mapa = $this->mapaClassificacao($empresaId);

        $comp = $this->agregarTitulosCompetencia($empresaId, $periodoDe, $periodoAte, $mapa);
        $cp = $this->agregarPagamentosCp($empresaId, $periodoDe, $periodoAte, $mapa);
        $cx = $this->agregarCaixaSemEspelhoCp($empresaId, $periodoDe, $periodoAte, $mapa);

        $bucketsCaixa = $this->somarBuckets($cp['por_classe'], $cx['por_classe']);

        $opCaixa = TefMoney::add(
            $bucketsCaixa[DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL],
            TefMoney::add(
                $bucketsCaixa[DrePlanoClassificacaoCatalog::DESPESA_ADMINISTRATIVA],
                $bucketsCaixa[DrePlanoClassificacaoCatalog::DESPESA_COMERCIAL]
            )
        );
        $finCaixa = $bucketsCaixa[DrePlanoClassificacaoCatalog::DESPESA_FINANCEIRA];
        $custoCaixa = $bucketsCaixa[DrePlanoClassificacaoCatalog::CUSTO];
        $naoClassCaixa = $bucketsCaixa[DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO];
        $totalDespCaixa = TefMoney::add($opCaixa, $finCaixa);

        $opComp = TefMoney::add(
            $comp['por_classe'][DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL],
            TefMoney::add(
                $comp['por_classe'][DrePlanoClassificacaoCatalog::DESPESA_ADMINISTRATIVA],
                $comp['por_classe'][DrePlanoClassificacaoCatalog::DESPESA_COMERCIAL]
            )
        );
        $finComp = $comp['por_classe'][DrePlanoClassificacaoCatalog::DESPESA_FINANCEIRA];
        $custoComp = $comp['por_classe'][DrePlanoClassificacaoCatalog::CUSTO];
        $naoClassComp = $comp['por_classe'][DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO];
        $competenciaHabilitada = Schema::hasTable('contas_pagar')
            && Schema::hasColumn('contas_pagar', 'competencia');

        return [
            'empresa_id' => $empresaId,
            'periodo_de' => $periodoDe,
            'periodo_ate' => $periodoAte,
            'competencia_disponivel' => $competenciaHabilitada,
            'competencia_motivo' => $competenciaHabilitada
                ? 'Apuração por contas_pagar.competencia (título). Vencimento/pagamento não definem competência.'
                : 'Campo competência indisponível no banco.',
            'despesas_operacionais_competencia' => $competenciaHabilitada ? $opComp : null,
            'despesas_operacionais' => $competenciaHabilitada ? $comp['por_classe'][DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL] : null,
            'despesas_administrativas' => $competenciaHabilitada ? $comp['por_classe'][DrePlanoClassificacaoCatalog::DESPESA_ADMINISTRATIVA] : null,
            'despesas_comerciais' => $competenciaHabilitada ? $comp['por_classe'][DrePlanoClassificacaoCatalog::DESPESA_COMERCIAL] : null,
            'despesas_financeiras_competencia' => $competenciaHabilitada ? $finComp : null,
            'custos_competencia' => $competenciaHabilitada ? $custoComp : null,
            'despesas_nao_classificadas_competencia' => $competenciaHabilitada ? $naoClassComp : null,
            'pendencias_sem_competencia_qtd' => $comp['pendencias_qtd'],
            'pendencias_sem_competencia_valor' => $comp['pendencias_valor'],
            'caixa_despesas_operacionais' => $bucketsCaixa[DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL],
            'caixa_despesas_administrativas' => $bucketsCaixa[DrePlanoClassificacaoCatalog::DESPESA_ADMINISTRATIVA],
            'caixa_despesas_comerciais' => $bucketsCaixa[DrePlanoClassificacaoCatalog::DESPESA_COMERCIAL],
            'caixa_despesas_financeiras' => $finCaixa,
            'caixa_despesas_operacionais_grupo' => $opCaixa,
            'caixa_custos' => $custoCaixa,
            'caixa_nao_classificadas' => $naoClassCaixa,
            'caixa_despesas_total_informativo' => $totalDespCaixa,
            'caixa_excluidos' => [
                'transferencias' => TefMoney::normalize($cx['excluidos']['transferencias'] ?? 0),
                'espelho_cp' => TefMoney::normalize($cx['excluidos']['espelho_cp'] ?? 0),
                'patrimonial' => TefMoney::normalize(
                    TefMoney::add(
                        $cp['por_classe'][DrePlanoClassificacaoCatalog::PATRIMONIAL] ?? '0.00',
                        TefMoney::add(
                            $cx['por_classe'][DrePlanoClassificacaoCatalog::PATRIMONIAL] ?? '0.00',
                            $comp['por_classe'][DrePlanoClassificacaoCatalog::PATRIMONIAL] ?? '0.00'
                        )
                    )
                ),
                'receita_deducao_em_saida' => TefMoney::normalize(
                    TefMoney::add(
                        $cp['por_classe'][DrePlanoClassificacaoCatalog::RECEITA] ?? '0.00',
                        TefMoney::add(
                            $cp['por_classe'][DrePlanoClassificacaoCatalog::DEDUCAO] ?? '0.00',
                            TefMoney::add(
                                $cx['por_classe'][DrePlanoClassificacaoCatalog::RECEITA] ?? '0.00',
                                $cx['por_classe'][DrePlanoClassificacaoCatalog::DEDUCAO] ?? '0.00'
                            )
                        )
                    )
                ),
            ],
            'contagens' => [
                'titulos_competencia' => $comp['qtd'],
                'titulos_em_aberto_competencia' => $comp['qtd_abertos'],
                'pagamentos_cp' => $cp['qtd'],
                'caixa_saidas_operacionais' => $cx['qtd'],
                'caixa_transferencias_excluidas' => (int) ($cx['excluidos']['qtd_transferencias'] ?? 0),
                'caixa_espelho_cp_excluidos' => (int) ($cx['excluidos']['qtd_espelho_cp'] ?? 0),
                'pendencias_sem_competencia' => $comp['pendencias_qtd'],
            ],
            'planos_classificados' => count(array_filter(
                $mapa,
                static fn (string $c): bool => $c !== DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO
            )),
            'fontes' => [
                'competencia' => 'contas_pagar.competencia + valor do título (valor−desconto+juros+multa);'
                    .' plano do título; inclui abertos; não usa vencimento/pagamento',
                'cp_caixa' => 'conta_pagar_pagamentos.data (valor_pago) — só visão caixa',
                'caixa' => 'caixa_lancamentos.saida por emissao, excluindo TRF- e espelho baixa:{id}',
                'exclusoes' => 'transferências, patrimonial, receita/dedução, CMV automático, OFX/TEF lote',
            ],
        ];
    }

    /**
     * Títulos CP por competência (uma vez por título — sem pagamentos/caixa).
     *
     * @param  array<int, string>  $mapa
     * @return array{
     *   por_classe: array<string, string>,
     *   qtd: int,
     *   qtd_abertos: int,
     *   pendencias_qtd: int,
     *   pendencias_valor: string
     * }
     */
    private function agregarTitulosCompetencia(int $empresaId, string $de, string $ate, array $mapa): array
    {
        $buckets = $this->bucketsZerados();
        $pendenciasQtd = 0;
        $pendenciasValor = '0.00';
        $qtd = 0;
        $qtdAbertos = 0;

        if (! Schema::hasTable('contas_pagar') || ! Schema::hasColumn('contas_pagar', 'competencia')) {
            return [
                'por_classe' => $buckets,
                'qtd' => 0,
                'qtd_abertos' => 0,
                'pendencias_qtd' => 0,
                'pendencias_valor' => '0.00',
            ];
        }

        $hasPlano = Schema::hasColumn('contas_pagar', 'plano_conta_id');
        $select = ['id', 'valor', 'desconto', 'juros', 'valor_pago', 'competencia'];
        if (Schema::hasColumn('contas_pagar', 'multa')) {
            $select[] = 'multa';
        }
        if ($hasPlano) {
            $select[] = 'plano_conta_id';
        }

        // Pendências (sem competência) — só contagem/valor para alerta; não entram no total.
        $pendQuery = DB::table('contas_pagar')
            ->where('empresa_id', $empresaId)
            ->whereNull('competencia');
        $pendenciasQtd = (int) (clone $pendQuery)->count();
        if ($pendenciasQtd > 0) {
            $pendRow = (clone $pendQuery)
                ->selectRaw('COALESCE(SUM(valor - desconto + juros'
                    .(Schema::hasColumn('contas_pagar', 'multa') ? ' + COALESCE(multa,0)' : '')
                    .'), 0) as total')
                ->first();
            $pendenciasValor = TefMoney::normalize($pendRow->total ?? 0);
        }

        $rows = DB::table('contas_pagar')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('competencia')
            ->whereDate('competencia', '>=', $de)
            ->whereDate('competencia', '<=', $ate)
            ->select($select)
            ->get();

        foreach ($rows as $row) {
            $valor = (float) $row->valor
                - (float) $row->desconto
                + (float) $row->juros
                + (float) ($row->multa ?? 0);
            if ($valor <= 0) {
                continue;
            }

            $planoId = $hasPlano ? (int) ($row->plano_conta_id ?? 0) : 0;
            $classe = $planoId > 0
                ? ($mapa[$planoId] ?? DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO)
                : DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO;

            // Patrimonial / receita / dedução não entram nas despesas do resultado.
            $buckets[$classe] = TefMoney::add($buckets[$classe], (string) $valor);
            $qtd++;
            if ((float) $row->valor_pago + 0.00001 < $valor) {
                $qtdAbertos++;
            }
        }

        return [
            'por_classe' => $buckets,
            'qtd' => $qtd,
            'qtd_abertos' => $qtdAbertos,
            'pendencias_qtd' => $pendenciasQtd,
            'pendencias_valor' => $pendenciasValor,
        ];
    }

    /**
     * @param  array<int, string>  $mapa  plano_conta_id => classificacao
     * @return array{por_classe: array<string, string>, qtd: int}
     */
    private function agregarPagamentosCp(int $empresaId, string $de, string $ate, array $mapa): array
    {
        $buckets = $this->bucketsZerados();
        if (! Schema::hasTable('conta_pagar_pagamentos') || ! Schema::hasTable('contas_pagar')) {
            return ['por_classe' => $buckets, 'qtd' => 0];
        }

        $rows = DB::table('conta_pagar_pagamentos as p')
            ->join('contas_pagar as cp', 'cp.id', '=', 'p.conta_pagar_id')
            ->where('cp.empresa_id', $empresaId)
            ->whereDate('p.data', '>=', $de)
            ->whereDate('p.data', '<=', $ate)
            ->where('p.valor_pago', '>', 0)
            ->select(['p.plano_conta_id', 'p.valor_pago'])
            ->get();

        $qtd = 0;
        foreach ($rows as $row) {
            $planoId = (int) ($row->plano_conta_id ?? 0);
            $classe = $planoId > 0
                ? ($mapa[$planoId] ?? DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO)
                : DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO;
            $buckets[$classe] = TefMoney::add($buckets[$classe], (string) $row->valor_pago);
            $qtd++;
        }

        return ['por_classe' => $buckets, 'qtd' => $qtd];
    }

    /**
     * Saídas de caixa sem espelho de baixa CP e sem transferência.
     *
     * @param  array<int, string>  $mapa
     * @return array{
     *   por_classe: array<string, string>,
     *   qtd: int,
     *   excluidos: array<string, float|int>
     * }
     */
    private function agregarCaixaSemEspelhoCp(int $empresaId, string $de, string $ate, array $mapa): array
    {
        $buckets = $this->bucketsZerados();
        $excluidos = [
            'transferencias' => 0.0,
            'espelho_cp' => 0.0,
            'qtd_transferencias' => 0,
            'qtd_espelho_cp' => 0,
        ];

        if (! Schema::hasTable('caixa_lancamentos')) {
            return ['por_classe' => $buckets, 'qtd' => 0, 'excluidos' => $excluidos];
        }

        $query = DB::table('caixa_lancamentos')
            ->where('empresa_id', $empresaId)
            ->where('saida', '>', 0)
            ->whereDate('emissao', '>=', $de)
            ->whereDate('emissao', '<=', $ate)
            ->select(['id', 'documento', 'historico', 'plano_conta_id', 'saida']);

        $qtd = 0;
        foreach ($query->cursor() as $row) {
            $saida = (float) $row->saida;
            if (CaixaTransferencia::isDocumentoTransferencia($row->documento ?? null)
                || $this->historicoTransferencia((string) ($row->historico ?? ''))
            ) {
                $excluidos['transferencias'] += $saida;
                $excluidos['qtd_transferencias']++;

                continue;
            }

            if ($this->historicoEspelhoBaixaCp((string) ($row->historico ?? ''))) {
                $excluidos['espelho_cp'] += $saida;
                $excluidos['qtd_espelho_cp']++;

                continue;
            }

            $planoId = (int) ($row->plano_conta_id ?? 0);
            $classe = $planoId > 0
                ? ($mapa[$planoId] ?? DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO)
                : DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO;
            $buckets[$classe] = TefMoney::add($buckets[$classe], (string) $row->saida);
            $qtd++;
        }

        return ['por_classe' => $buckets, 'qtd' => $qtd, 'excluidos' => $excluidos];
    }

    /**
     * @return array<int, string> plano_conta_id => classificacao
     */
    public function mapaClassificacao(int $empresaId): array
    {
        if ($empresaId <= 0 || ! Schema::hasTable('dre_plano_classificacoes')) {
            return [];
        }

        $mapa = [];
        DrePlanoClassificacao::query()
            ->where('empresa_id', $empresaId)
            ->select(['plano_conta_id', 'classificacao'])
            ->orderBy('id')
            ->each(function (DrePlanoClassificacao $row) use (&$mapa): void {
                $mapa[(int) $row->plano_conta_id] = DrePlanoClassificacaoCatalog::normalize($row->classificacao);
            });

        return $mapa;
    }

    /**
     * Grava/atualiza classificação (não altera planos_contas).
     */
    public function salvarClassificacao(int $empresaId, int $planoContaId, string $classificacao): void
    {
        if ($empresaId <= 0 || $planoContaId <= 0) {
            return;
        }

        $classificacao = DrePlanoClassificacaoCatalog::normalize($classificacao);

        // Persiste inclusive "nao_classificado" como escolha explícita,
        // para o "Aplicar padrão" não sobrescrever depois.
        DrePlanoClassificacao::query()->updateOrCreate(
            [
                'empresa_id' => $empresaId,
                'plano_conta_id' => $planoContaId,
            ],
            ['classificacao' => $classificacao]
        );
    }

    private function historicoEspelhoBaixaCp(string $historico): bool
    {
        return preg_match('/\bbaixa:\d+\s*$/i', $historico) === 1;
    }

    private function historicoTransferencia(string $historico): bool
    {
        return str_starts_with(mb_strtoupper(trim($historico), 'UTF-8'), CaixaTransferencia::HISTORICO_PREFIXO);
    }

    /**
     * @return array<string, string>
     */
    private function bucketsZerados(): array
    {
        $out = [];
        foreach (DrePlanoClassificacaoCatalog::keys() as $key) {
            $out[$key] = '0.00';
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $a
     * @param  array<string, string>  $b
     * @return array<string, string>
     */
    private function somarBuckets(array $a, array $b): array
    {
        $out = $this->bucketsZerados();
        foreach ($out as $key => $_) {
            $out[$key] = TefMoney::add($a[$key] ?? '0.00', $b[$key] ?? '0.00');
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function vazio(int $empresaId, string $de, string $ate): array
    {
        $z = '0.00';

        return [
            'empresa_id' => $empresaId,
            'periodo_de' => $de,
            'periodo_ate' => $ate,
            'competencia_disponivel' => false,
            'competencia_motivo' => 'Período ou empresa inválidos.',
            'despesas_operacionais_competencia' => null,
            'despesas_operacionais' => null,
            'despesas_administrativas' => null,
            'despesas_comerciais' => null,
            'despesas_financeiras_competencia' => null,
            'custos_competencia' => null,
            'despesas_nao_classificadas_competencia' => null,
            'pendencias_sem_competencia_qtd' => 0,
            'pendencias_sem_competencia_valor' => $z,
            'caixa_despesas_operacionais' => $z,
            'caixa_despesas_administrativas' => $z,
            'caixa_despesas_comerciais' => $z,
            'caixa_despesas_financeiras' => $z,
            'caixa_despesas_operacionais_grupo' => $z,
            'caixa_custos' => $z,
            'caixa_nao_classificadas' => $z,
            'caixa_despesas_total_informativo' => $z,
            'caixa_excluidos' => [
                'transferencias' => $z,
                'espelho_cp' => $z,
                'patrimonial' => $z,
                'receita_deducao_em_saida' => $z,
            ],
            'contagens' => [
                'titulos_competencia' => 0,
                'titulos_em_aberto_competencia' => 0,
                'pagamentos_cp' => 0,
                'caixa_saidas_operacionais' => 0,
                'caixa_transferencias_excluidas' => 0,
                'caixa_espelho_cp_excluidos' => 0,
                'pendencias_sem_competencia' => 0,
            ],
            'planos_classificados' => 0,
            'fontes' => [],
        ];
    }
}
