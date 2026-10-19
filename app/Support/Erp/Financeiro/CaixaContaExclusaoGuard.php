<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaConta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Impede exclusão de conta caixa com qualquer vínculo (financeiro, PDV, operadores, formas)
 * e de contas estruturais do sistema (CAIXA GERAL).
 *
 * Não remove nem altera históricos: só informa o bloqueio ou autoriza o delete do cadastro.
 */
final class CaixaContaExclusaoGuard
{
    /**
     * Motivo legível se a exclusão for bloqueada; null se puder excluir.
     * Considera todos os registros (inclusive cancelados / estornados / sessões fechadas).
     */
    public static function motivoBloqueio(CaixaConta $conta): ?string
    {
        if ($conta->isSistema()) {
            return 'O CAIXA GERAL (SISTEMA) é uma conta estrutural obrigatória e não pode ser excluída.';
        }

        $vinculos = self::listarVinculos($conta);

        if ($vinculos === []) {
            return null;
        }

        return 'Não é possível excluir: conta vinculada a '.implode('; ', $vinculos).'.';
    }

    /**
     * @return list<string>
     */
    public static function listarVinculos(CaixaConta $conta): array
    {
        $id = (int) $conta->id;
        $vinculos = [];

        self::appendContagem(
            $vinculos,
            'lançamentos do Livro Caixa',
            self::countIfColumn('caixa_lancamentos', 'caixa_conta_id', $id),
        );
        self::appendContagem(
            $vinculos,
            'baixas a receber',
            self::countIfColumn('conta_receber_pagamentos', 'caixa_conta_id', $id),
        );
        self::appendContagem(
            $vinculos,
            'baixas a pagar',
            self::countIfColumn('conta_pagar_pagamentos', 'caixa_conta_id', $id),
        );
        self::appendContagem(
            $vinculos,
            'sessões de caixa do PDV',
            self::countIfColumn('pdv_caixa_sessoes', 'caixa_conta_id', $id),
        );
        self::appendContagem(
            $vinculos,
            'operadores / usuários liberados',
            self::countIfColumn('caixa_conta_user', 'caixa_conta_id', $id),
        );
        self::appendContagem(
            $vinculos,
            'vendedores (empresa)',
            self::countIfColumn('empresa_vendedor', 'caixa_conta_id', $id),
        );
        self::appendContagem(
            $vinculos,
            'formas de pagamento (conta destino)',
            self::countIfColumn('formas_pagamento', 'conta_destino_id', $id),
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
}
