<?php

namespace App\Support\Erp\Dre;

use App\Models\PlanoConta;
use Illuminate\Support\Facades\Schema;

/**
 * Aplica classificações DRE padrão sugeridas, preservando as já configuradas.
 * Elegível = plano ativo sem linha em dre_plano_classificacoes.
 */
final class DrePlanoClassificacaoPadraoService
{
    public function __construct(
        private readonly DreDespesasGerenciaisService $despesas,
    ) {
    }

    /**
     * Prévia do que seria aplicado (só planos ainda sem classificação gravada).
     *
     * @return array{
     *   itens: list<array{
     *     plano_conta_id: int,
     *     codigo: int|string|null,
     *     descricao: string,
     *     classificacao_atual: string,
     *     label_atual: string,
     *     classificacao_sugerida: string,
     *     label_sugerida: string
     *   }>,
     *   resumo: array{
     *     aplicar: int,
     *     preservados: int,
     *     ignorados: int,
     *     total_ativos: int
     *   }
     * }
     */
    public function preverDetalhado(int $empresaId): array
    {
        $vazio = [
            'itens' => [],
            'resumo' => [
                'aplicar' => 0,
                'preservados' => 0,
                'ignorados' => 0,
                'total_ativos' => 0,
            ],
        ];

        if ($empresaId <= 0 || ! Schema::hasTable('planos_contas')) {
            return $vazio;
        }

        $mapa = $this->despesas->mapaClassificacao($empresaId);
        $labels = DrePlanoClassificacaoCatalog::labels();
        $itens = [];
        $preservados = 0;
        $ignorados = 0;

        $planos = PlanoConta::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao']);

        foreach ($planos as $plano) {
            $id = (int) $plano->id;

            // Qualquer linha gravada (inclusive "nao_classificado") é escolha do usuário.
            if (array_key_exists($id, $mapa)) {
                $preservados++;

                continue;
            }

            $sugerida = DrePlanoClassificacaoSugestoes::sugerir([
                'id' => $id,
                'codigo' => $plano->codigo,
                'descricao' => (string) $plano->descricao,
            ]);
            if ($sugerida === null) {
                $ignorados++;

                continue;
            }

            $atual = DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO;
            $itens[] = [
                'plano_conta_id' => $id,
                'codigo' => $plano->codigo,
                'descricao' => (string) $plano->descricao,
                'classificacao_atual' => $atual,
                'label_atual' => $labels[$atual] ?? $atual,
                'classificacao_sugerida' => $sugerida,
                'label_sugerida' => $labels[$sugerida] ?? $sugerida,
            ];
        }

        return [
            'itens' => $itens,
            'resumo' => [
                'aplicar' => count($itens),
                'preservados' => $preservados,
                'ignorados' => $ignorados,
                'total_ativos' => $planos->count(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function prever(int $empresaId): array
    {
        return $this->preverDetalhado($empresaId)['itens'];
    }

    /**
     * Aplica sugestões apenas onde ainda não há classificação gravada.
     *
     * @return array{aplicados: int, preservados: int, sem_sugestao: int}
     */
    public function aplicar(int $empresaId): array
    {
        $detalhe = $this->preverDetalhado($empresaId);
        $aplicados = 0;
        foreach ($detalhe['itens'] as $row) {
            $this->despesas->salvarClassificacao(
                $empresaId,
                (int) $row['plano_conta_id'],
                (string) $row['classificacao_sugerida'],
            );
            $aplicados++;
        }

        return [
            'aplicados' => $aplicados,
            'preservados' => (int) $detalhe['resumo']['preservados'],
            'sem_sugestao' => (int) $detalhe['resumo']['ignorados'],
        ];
    }

    /** Classificações iniciais seguras para empresa nova (não sobrescreve). */
    public function aplicarIniciaisSeguras(int $empresaId): int
    {
        return $this->aplicar($empresaId)['aplicados'];
    }
}
