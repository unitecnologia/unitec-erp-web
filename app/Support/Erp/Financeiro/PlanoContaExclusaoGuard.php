<?php

namespace App\Support\Erp\Financeiro;

use App\Models\PlanoConta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Impede exclusão de plano com qualquer vínculo (FK, código PDV ou texto de caixa)
 * e de contas padrão do chart oficial do ERP.
 */
final class PlanoContaExclusaoGuard
{
    /** Códigos do plano padrão (scripts/incluir-planos-contas.*) — necessários ao ERP. */
    public const CODIGOS_PADRAO = [
        101, 102, 103, 104, 105,
        201, 202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215,
    ];

    public static function ehContaPadrao(PlanoConta $plano): bool
    {
        return in_array((int) $plano->codigo, self::CODIGOS_PADRAO, true);
    }

    /**
     * Motivo legível se a exclusão for bloqueada; null se puder excluir.
     * Considera todos os registros (inclusive cancelados / estornados).
     */
    public static function motivoBloqueio(PlanoConta $plano): ?string
    {
        if (self::ehContaPadrao($plano)) {
            return 'Esta é uma conta padrão do ERP e não pode ser excluída.';
        }

        $vinculos = self::listarVinculos($plano);

        if ($vinculos === []) {
            return null;
        }

        return 'Não é possível excluir: conta vinculada a '.implode('; ', $vinculos).'.';
    }

    /**
     * @return list<string>
     */
    public static function listarVinculos(PlanoConta $plano): array
    {
        $id = (int) $plano->id;
        $codigo = (int) $plano->codigo;
        $descricao = mb_strtoupper(trim((string) $plano->descricao), 'UTF-8');
        $vinculos = [];

        self::appendContagem(
            $vinculos,
            'parâmetros da empresa',
            self::countEmpresaParams($id),
        );
        self::appendContagem(
            $vinculos,
            'Livro Caixa',
            self::countCaixaLancamentos($id, $descricao),
        );
        self::appendContagem(
            $vinculos,
            'Contas a Receber',
            self::countIfColumn('contas_receber', 'plano_conta_id', $id),
        );
        self::appendContagem(
            $vinculos,
            'baixas a receber',
            self::countIfColumn('conta_receber_pagamentos', 'plano_conta_id', $id),
        );
        self::appendContagem(
            $vinculos,
            'baixas a pagar',
            self::countIfColumn('conta_pagar_pagamentos', 'plano_conta_id', $id),
        );
        self::appendContagem(
            $vinculos,
            'movimentos de caixa PDV',
            self::countPdvMovimentos($codigo),
        );

        return $vinculos;
    }

    /**
     * @param  list<string>  $vinculos
     */
    private static function appendContagem(array &$vinculos, string $rotulo, int $qtd): void
    {
        if ($qtd <= 0) {
            return;
        }

        $vinculos[] = $rotulo.' ('.$qtd.')';
    }

    private static function countIfColumn(string $table, string $column, int $id): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->where($column, $id)->count();
    }

    private static function countEmpresaParams(int $id): int
    {
        if (! Schema::hasTable('empresas')) {
            return 0;
        }

        $cols = [
            'param_plano_conta_venda_id',
            'param_plano_conta_compra_id',
            'param_plano_conta_taxa_cartao_id',
        ];

        $total = 0;

        foreach ($cols as $col) {
            if (! Schema::hasColumn('empresas', $col)) {
                continue;
            }

            $total += (int) DB::table('empresas')->where($col, $id)->count();
        }

        return $total;
    }

    private static function countCaixaLancamentos(int $id, string $descricao): int
    {
        if (! Schema::hasTable('caixa_lancamentos')) {
            return 0;
        }

        $query = DB::table('caixa_lancamentos');

        if (Schema::hasColumn('caixa_lancamentos', 'plano_conta_id')) {
            $query->where(function ($q) use ($id, $descricao): void {
                $q->where('plano_conta_id', $id);

                // Texto histórico sem FK (lançamentos antigos / denormalizados).
                if (
                    $descricao !== ''
                    && Schema::hasColumn('caixa_lancamentos', 'plano_contas')
                ) {
                    $q->orWhere(function ($texto) use ($descricao): void {
                        $texto->whereNull('plano_conta_id')
                            ->whereRaw('UPPER(TRIM(plano_contas)) = ?', [$descricao]);
                    });
                }
            });
        } elseif (Schema::hasColumn('caixa_lancamentos', 'plano_contas') && $descricao !== '') {
            $query->whereRaw('UPPER(TRIM(plano_contas)) = ?', [$descricao]);
        } else {
            return 0;
        }

        return (int) $query->count();
    }

    private static function countPdvMovimentos(int $codigo): int
    {
        if (
            $codigo <= 0
            || ! Schema::hasTable('pdv_caixa_movimentos')
            || ! Schema::hasColumn('pdv_caixa_movimentos', 'plano_conta_codigo')
        ) {
            return 0;
        }

        return (int) DB::table('pdv_caixa_movimentos')
            ->where('plano_conta_codigo', $codigo)
            ->count();
    }
}
