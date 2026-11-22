<?php

namespace App\Support\Erp\Tef;

use App\Models\Terminal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Formas SUPERTEF DEBITO/CREDITO: só existem ativas quando SuperTEF está habilitado.
 * Tabela global (sem empresa_id) — não exclui registros nem mexe em pagamentos históricos.
 */
final class SuperTefFormasPagamento
{
    public const DEBITO = 'SUPERTEF DEBITO';

    public const CREDITO = 'SUPERTEF CREDITO';

    /**
     * Garante as duas formas ativas e visíveis na venda (cria se faltar).
     */
    public static function ensureAtivas(): void
    {
        if (! Schema::hasTable('formas_pagamento')) {
            return;
        }

        self::ensureDebito();
        self::ensureCredito();
    }

    /**
     * Há ao menos um terminal com SuperTEF ativo (provedor + flag).
     */
    public static function existeTerminalSuperTefHabilitado(): bool
    {
        if (! Schema::hasTable('terminais') || ! Schema::hasColumn('terminais', 'tef_extra')) {
            return false;
        }

        $terminais = Terminal::query()
            ->whereNotNull('tef_extra')
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'tef_extra']);

        foreach ($terminais as $terminal) {
            $cfg = SuperTefTerminalConfig::read($terminal, withSecret: false);

            if ($cfg['ativo'] && TefProviderId::normalize($cfg['provedor']) === TefProviderId::SUPERTEF) {
                return true;
            }
        }

        return false;
    }

    /**
     * Desativa SUPERTEF DEBITO/CREDITO quando nenhum terminal SuperTEF está habilitado.
     * Não exclui. Retorna quantas linhas foram desativadas.
     */
    public static function desativarSeSemSuperTef(): int
    {
        if (! Schema::hasTable('formas_pagamento') || self::existeTerminalSuperTefHabilitado()) {
            return 0;
        }

        $q = DB::table('formas_pagamento')
            ->where('usa_super_tef', true)
            ->where(function ($query): void {
                $query->whereIn('descricao', [
                    self::DEBITO,
                    'SUPERTEF DÉBITO',
                    self::CREDITO,
                    'SUPERTEF CRÉDITO',
                ]);
            })
            ->where(function ($query): void {
                $query->where('ativo', true)->orWhere('aparece_venda', true);
            });

        $ids = (clone $q)->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        $upd = [
            'ativo' => false,
            'aparece_venda' => false,
            'updated_at' => now(),
        ];

        return DB::table('formas_pagamento')->whereIn('id', $ids)->update($upd);
    }

    private static function ensureDebito(): void
    {
        $row = self::findByDescricoes([self::DEBITO, 'SUPERTEF DÉBITO']);

        if ($row !== null) {
            self::reativar($row->id, [
                'tipo' => 'cartao_debito',
                'usa_super_tef' => true,
                'max_parcelas' => 1,
            ]);

            return;
        }

        self::insertForma([
            'descricao' => self::DEBITO,
            'tipo' => 'cartao_debito',
            'max_parcelas' => 1,
            'modo_prazo' => null,
        ]);
    }

    private static function ensureCredito(): void
    {
        $row = self::findByDescricoes([self::CREDITO, 'SUPERTEF CRÉDITO']);

        if ($row !== null) {
            self::reativar($row->id, [
                'tipo' => 'cartao_credito',
                'usa_super_tef' => true,
                'max_parcelas' => max(1, (int) ($row->max_parcelas ?? 12)),
                'modo_prazo' => 'financeiro',
            ]);

            return;
        }

        self::insertForma([
            'descricao' => self::CREDITO,
            'tipo' => 'cartao_credito',
            'max_parcelas' => 12,
            'modo_prazo' => 'financeiro',
        ]);
    }

    /**
     * @param  list<string>  $descricoes
     */
    private static function findByDescricoes(array $descricoes): ?object
    {
        return DB::table('formas_pagamento')
            ->whereIn('descricao', $descricoes)
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private static function reativar(int $id, array $extra = []): void
    {
        $upd = array_merge([
            'ativo' => true,
            'aparece_venda' => true,
            'usa_super_tef' => true,
            'updated_at' => now(),
        ], $extra);

        if (! Schema::hasColumn('formas_pagamento', 'modo_prazo')) {
            unset($upd['modo_prazo']);
        }

        DB::table('formas_pagamento')->where('id', $id)->update($upd);
    }

    /**
     * @param  array{descricao: string, tipo: string, max_parcelas: int, modo_prazo: ?string}  $spec
     */
    private static function insertForma(array $spec): void
    {
        $maxCodigo = (int) DB::table('formas_pagamento')->max('codigo');

        $row = [
            'codigo' => $maxCodigo > 0 ? $maxCodigo + 1 : 90,
            'descricao' => $spec['descricao'],
            'tipo' => $spec['tipo'],
            'ativo' => true,
            'aparece_venda' => true,
            'usa_tef' => false,
            'usa_super_tef' => true,
            'gerar_qrcode_pdv' => false,
            'aparece_contas_receber' => false,
            'tipo_movimento' => 'caixa',
            'max_parcelas' => $spec['max_parcelas'],
            'prazo_cartao' => 0,
            'intervalo_parcelas' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('formas_pagamento', 'modo_prazo') && $spec['modo_prazo'] !== null) {
            $row['modo_prazo'] = $spec['modo_prazo'];
        }

        if (Schema::hasColumn('formas_pagamento', 'atalho')) {
            $row['atalho'] = '';
        }

        DB::table('formas_pagamento')->insert($row);
    }
}
