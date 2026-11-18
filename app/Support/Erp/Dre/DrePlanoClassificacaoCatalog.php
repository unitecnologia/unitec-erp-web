<?php

namespace App\Support\Erp\Dre;

/**
 * D2.3 — Catálogo estável de classificação DRE do plano (isolado do cadastro).
 */
final class DrePlanoClassificacaoCatalog
{
    public const RECEITA = 'receita';

    public const DEDUCAO = 'deducao';

    public const CUSTO = 'custo';

    public const DESPESA_OPERACIONAL = 'despesa_operacional';

    public const DESPESA_ADMINISTRATIVA = 'despesa_administrativa';

    public const DESPESA_COMERCIAL = 'despesa_comercial';

    public const DESPESA_FINANCEIRA = 'despesa_financeira';

    /** Aportes, retiradas, empréstimos (principal), transferências — fora do resultado. */
    public const PATRIMONIAL = 'patrimonial';

    public const NAO_CLASSIFICADO = 'nao_classificado';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::RECEITA,
            self::DEDUCAO,
            self::CUSTO,
            self::DESPESA_OPERACIONAL,
            self::DESPESA_ADMINISTRATIVA,
            self::DESPESA_COMERCIAL,
            self::DESPESA_FINANCEIRA,
            self::PATRIMONIAL,
            self::NAO_CLASSIFICADO,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::RECEITA => 'Receita',
            self::DEDUCAO => 'Dedução',
            self::CUSTO => 'Custo',
            self::DESPESA_OPERACIONAL => 'Despesa operacional',
            self::DESPESA_ADMINISTRATIVA => 'Despesa administrativa',
            self::DESPESA_COMERCIAL => 'Despesa comercial',
            self::DESPESA_FINANCEIRA => 'Despesa financeira',
            self::PATRIMONIAL => 'Patrimonial / não compõe DRE',
            self::NAO_CLASSIFICADO => 'Não classificado',
        ];
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /** Classes que entram na visão caixa de despesas (não no resultado por competência). */
    public static function isDespesaCaixa(string $key): bool
    {
        return in_array($key, [
            self::DESPESA_OPERACIONAL,
            self::DESPESA_ADMINISTRATIVA,
            self::DESPESA_COMERCIAL,
            self::DESPESA_FINANCEIRA,
        ], true);
    }

    public static function isOperacionalGrupo(string $key): bool
    {
        return in_array($key, [
            self::DESPESA_OPERACIONAL,
            self::DESPESA_ADMINISTRATIVA,
            self::DESPESA_COMERCIAL,
        ], true);
    }

    public static function normalize(?string $key): string
    {
        $key = strtolower(trim((string) $key));

        return self::isValid($key) ? $key : self::NAO_CLASSIFICADO;
    }
}
