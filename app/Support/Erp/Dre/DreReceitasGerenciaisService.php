<?php

namespace App\Support\Erp\Dre;

use App\Models\DevolucaoVenda;
use App\Models\OrdemServico;
use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\Tef\TefMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D2.1 — Receitas gerenciais por competência (somente leitura/cálculo).
 *
 * Não lança financeiro, não altera vendas/PDV/OS, não usa C5D/TEF como faturamento,
 * não calcula impostos, CMV, despesas nem lucro.
 */
final class DreReceitasGerenciaisService
{
    /**
     * @return array{
     *   empresa_id: int,
     *   periodo_de: string,
     *   periodo_ate: string,
     *   regime: string,
     *   receita_mercadorias: string,
     *   receita_servicos: string,
     *   receita_pecas_os: string,
     *   descontos_embutidos_informativo: string,
     *   devolucoes: string,
     *   receita_bruta_operacional: string,
     *   receita_liquida_operacional: string,
     *   contagens: array<string, int>,
     *   ausencias: array<string, string>,
     *   fontes: array<string, string>,
     *   formulas: array<string, string>
     * }
     */
    public function calcular(int $empresaId, string $periodoDe, string $periodoAte): array
    {
        $periodoDe = substr(trim($periodoDe), 0, 10);
        $periodoAte = substr(trim($periodoAte), 0, 10);

        if ($empresaId <= 0 || $periodoDe === '' || $periodoAte === '' || $periodoDe > $periodoAte) {
            return $this->resultadoVazio($empresaId, $periodoDe, $periodoAte);
        }

        $merc = $this->agregarMercadorias($empresaId, $periodoDe, $periodoAte);
        $pdvOrfao = $this->agregarPdvSemEspelho($empresaId, $periodoDe, $periodoAte);
        $os = $this->agregarOrdensServico($empresaId, $periodoDe, $periodoAte);
        $desc = $this->agregarDescontosEmbutidosInformativo($empresaId, $periodoDe, $periodoAte);
        $dev = $this->agregarDevolucoes($empresaId, $periodoDe, $periodoAte);

        $receitaMercadorias = TefMoney::add($merc['total'], $pdvOrfao['total']);
        $receitaServicos = $os['servicos'];
        $receitaPecas = $os['pecas'];
        $descontosInfo = TefMoney::add($desc['total'], $pdvOrfao['desconto']);
        $devolucoes = $dev['total'];

        // Totais de venda/OS já são líquidos de desconto — não subtrair descontos novamente.
        $bruta = TefMoney::add($receitaMercadorias, TefMoney::add($receitaServicos, $receitaPecas));
        $liquida = TefMoney::sub($bruta, $devolucoes);
        if (TefMoney::cmp($liquida, '0') === -1) {
            $liquida = '0.00';
        }

        return [
            'empresa_id' => $empresaId,
            'periodo_de' => $periodoDe,
            'periodo_ate' => $periodoAte,
            'regime' => 'competencia',
            'receita_mercadorias' => $receitaMercadorias,
            'receita_servicos' => $receitaServicos,
            'receita_pecas_os' => $receitaPecas,
            'descontos_embutidos_informativo' => $descontosInfo,
            'devolucoes' => $devolucoes,
            'receita_bruta_operacional' => $bruta,
            'receita_liquida_operacional' => $liquida,
            'contagens' => [
                'vendas_fechadas' => $merc['qtd'],
                'pdv_sem_espelho' => $pdvOrfao['qtd'],
                'os_faturadas' => $os['qtd'],
                'devolucoes_finalizadas' => $dev['qtd'],
            ],
            'ausencias' => [
                'impostos' => 'Não calculados nesta etapa (D2.1).',
                'cmv' => 'Não disponível — sem custo histórico no item da venda.',
                'despesas' => 'Não calculadas nesta etapa.',
                'taxas_tef' => 'C5D/lote TEF/OFX não entram como faturamento.',
                'lucro' => 'Não calculado nesta etapa.',
                'despesas_caixa_vs_competencia' => 'Não misturar nesta etapa.',
            ],
            'fontes' => [
                'mercadorias' => 'vendas.status=fechado, plataforma≠os, competência=vendas.data'
                    .' + pdv_vendas comercial sem venda_id (órfão), competência=DATE(fechado_em)',
                'servicos_pecas' => 'ordens_servico situacao finalizada|entregue, data_termino;'
                    .' total_servicos e total_produtos (não total_geral); espelho vendas/plataforma=os excluído de mercadorias',
                'descontos' => 'Informativo (PDV.desconto); total da venda/OS já líquido — não re-subtrai',
                'devolucoes' => 'devolucoes_venda situacao=finalizada, competência=data',
                'excluidos' => 'CR, caixa, PDV-CX, NF-e/NFC-e, NFS-e, C5D, vendas canceladas, OS canceladas',
            ],
            'formulas' => [
                'receita_mercadorias' => 'Σ vendas.total (fechadas, ≠OS) + Σ pdv_vendas.total (órfãs comerciais)',
                'receita_servicos' => 'Σ ordens_servico.total_servicos',
                'receita_pecas_os' => 'Σ ordens_servico.total_produtos',
                'receita_bruta_operacional' => 'mercadorias + serviços + peças',
                'receita_liquida_operacional' => 'bruta − devoluções_finalizadas',
                'descontos' => 'somente informativo; não reduz a receita novamente',
            ],
        ];
    }

    /**
     * @return array{total: string, qtd: int}
     */
    private function agregarMercadorias(int $empresaId, string $de, string $ate): array
    {
        if (! Schema::hasTable('vendas')) {
            return ['total' => '0.00', 'qtd' => 0];
        }

        $row = Venda::query()
            ->where('empresa_id', $empresaId)
            ->where('status', Venda::STATUS_FECHADO)
            ->whereDate('data', '>=', $de)
            ->whereDate('data', '<=', $ate)
            // Espelho OS: receita vem de ordens_servico (serviços/peças), não da venda.
            ->where(function ($q): void {
                $q->whereNull('plataforma')
                    ->orWhere('plataforma', '')
                    ->orWhere('plataforma', '!=', Venda::PLATAFORMA_OS);
            })
            ->toBase()
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->first();

        return [
            'total' => TefMoney::normalize($row->total ?? 0),
            'qtd' => (int) ($row->qtd ?? 0),
        ];
    }

    /**
     * PDV comercial fechado sem espelho em vendas (falha/legado de mirror).
     *
     * @return array{total: string, desconto: string, qtd: int}
     */
    private function agregarPdvSemEspelho(int $empresaId, string $de, string $ate): array
    {
        if (! Schema::hasTable('pdv_vendas') || ! Schema::hasTable('pdv_caixa_sessoes')) {
            return ['total' => '0.00', 'desconto' => '0.00', 'qtd' => 0];
        }

        // Sem alias curto: com DB_PREFIX (unitec_), "pdv_vendas as p" vira unitec_p
        // e selectRaw("p.total") quebra (SQLSTATE 42S22). Nome completo + wrapTable no SUM.
        $pv = 'pdv_vendas';
        $cs = 'pdv_caixa_sessoes';
        $pvSql = $this->sqlTable($pv);

        $query = DB::table($pv)
            ->join($cs, "{$cs}.id", '=', "{$pv}.pdv_caixa_sessao_id")
            ->where("{$cs}.empresa_id", $empresaId)
            ->where("{$pv}.situacao", 'F')
            ->whereNull("{$pv}.venda_id")
            ->whereNotNull("{$pv}.fechado_em")
            ->whereDate("{$pv}.fechado_em", '>=', $de)
            ->whereDate("{$pv}.fechado_em", '<=', $ate);

        // Escopo comercial (igual PdvVenda::comercial) — evita regularização/OS NFC-e.
        if (Schema::hasColumn('pdv_vendas', 'origem')) {
            $query->where(function ($q) use ($pv): void {
                $q->whereNull("{$pv}.origem")
                    ->orWhereNotIn("{$pv}.origem", [
                        PdvVenda::ORIGEM_REGULARIZACAO_FISCAL,
                        PdvVenda::ORIGEM_OS_NFCE_PECAS,
                    ]);
            });
        }

        $row = $query
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw("COALESCE(SUM({$pvSql}.`total`), 0) as total")
            ->selectRaw("COALESCE(SUM({$pvSql}.`desconto`), 0) as desconto")
            ->first();

        return [
            'total' => TefMoney::normalize($row->total ?? 0),
            'desconto' => TefMoney::normalize($row->desconto ?? 0),
            'qtd' => (int) ($row->qtd ?? 0),
        ];
    }

    /**
     * @return array{servicos: string, pecas: string, qtd: int}
     */
    private function agregarOrdensServico(int $empresaId, string $de, string $ate): array
    {
        if (! Schema::hasTable('ordens_servico')) {
            return ['servicos' => '0.00', 'pecas' => '0.00', 'qtd' => 0];
        }

        // Mesma regra do FaturamentoServicosReport: finalizada/entregue + data_termino.
        // Não usa total_geral (evita somar total + partes).
        // Espelho vendas/plataforma=os fica de fora de mercadorias — sem duplicar.
        $row = OrdemServico::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('situacao', [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ])
            ->whereNotNull('data_termino')
            ->whereDate('data_termino', '>=', $de)
            ->whereDate('data_termino', '<=', $ate)
            ->toBase()
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw('COALESCE(SUM(total_servicos), 0) as servicos')
            ->selectRaw('COALESCE(SUM(total_produtos), 0) as pecas')
            ->first();

        return [
            'servicos' => TefMoney::normalize($row->servicos ?? 0),
            'pecas' => TefMoney::normalize($row->pecas ?? 0),
            'qtd' => (int) ($row->qtd ?? 0),
        ];
    }

    /**
     * Descontos já embutidos no total (PDV espelhado) — só transparência.
     *
     * @return array{total: string}
     */
    private function agregarDescontosEmbutidosInformativo(int $empresaId, string $de, string $ate): array
    {
        if (! Schema::hasTable('pdv_vendas')
            || ! Schema::hasTable('pdv_caixa_sessoes')
            || ! Schema::hasTable('vendas')
        ) {
            return ['total' => '0.00'];
        }

        $pv = 'pdv_vendas';
        $cs = 'pdv_caixa_sessoes';
        $v = 'vendas';
        $pvSql = $this->sqlTable($pv);

        $row = DB::table($pv)
            ->join($cs, "{$cs}.id", '=', "{$pv}.pdv_caixa_sessao_id")
            ->join($v, "{$v}.id", '=', "{$pv}.venda_id")
            ->where("{$cs}.empresa_id", $empresaId)
            ->where("{$v}.empresa_id", $empresaId)
            ->where("{$v}.status", Venda::STATUS_FECHADO)
            ->whereDate("{$v}.data", '>=', $de)
            ->whereDate("{$v}.data", '<=', $ate)
            ->where("{$pv}.situacao", 'F')
            ->where(function ($q) use ($v): void {
                $q->whereNull("{$v}.plataforma")
                    ->orWhere("{$v}.plataforma", '')
                    ->orWhere("{$v}.plataforma", '!=', Venda::PLATAFORMA_OS);
            })
            ->selectRaw("COALESCE(SUM({$pvSql}.`desconto`), 0) as desconto")
            ->first();

        return ['total' => TefMoney::normalize($row->desconto ?? 0)];
    }

    /** Nome físico com DB_PREFIX para DB::raw/selectRaw (Query Builder não prefixa raw). */
    private function sqlTable(string $table): string
    {
        return DB::connection()->getQueryGrammar()->wrapTable($table);
    }

    /**
     * @return array{total: string, qtd: int}
     */
    private function agregarDevolucoes(int $empresaId, string $de, string $ate): array
    {
        if (! Schema::hasTable('devolucoes_venda')) {
            return ['total' => '0.00', 'qtd' => 0];
        }

        $row = DevolucaoVenda::query()
            ->where('empresa_id', $empresaId)
            ->where('situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
            ->whereDate('data', '>=', $de)
            ->whereDate('data', '<=', $ate)
            ->toBase()
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->first();

        return [
            'total' => TefMoney::normalize($row->total ?? 0),
            'qtd' => (int) ($row->qtd ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultadoVazio(int $empresaId, string $de, string $ate): array
    {
        return [
            'empresa_id' => $empresaId,
            'periodo_de' => $de,
            'periodo_ate' => $ate,
            'regime' => 'competencia',
            'receita_mercadorias' => '0.00',
            'receita_servicos' => '0.00',
            'receita_pecas_os' => '0.00',
            'descontos_embutidos_informativo' => '0.00',
            'devolucoes' => '0.00',
            'receita_bruta_operacional' => '0.00',
            'receita_liquida_operacional' => '0.00',
            'contagens' => [
                'vendas_fechadas' => 0,
                'pdv_sem_espelho' => 0,
                'os_faturadas' => 0,
                'devolucoes_finalizadas' => 0,
            ],
            'ausencias' => [
                'impostos' => 'Não calculados nesta etapa (D2.1).',
                'cmv' => 'Não disponível — sem custo histórico no item da venda.',
                'despesas' => 'Não calculadas nesta etapa.',
                'taxas_tef' => 'C5D/lote TEF/OFX não entram como faturamento.',
                'lucro' => 'Não calculado nesta etapa.',
                'despesas_caixa_vs_competencia' => 'Não misturar nesta etapa.',
            ],
            'fontes' => [],
            'formulas' => [],
        ];
    }
}
