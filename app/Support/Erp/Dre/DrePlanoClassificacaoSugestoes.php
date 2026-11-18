<?php

namespace App\Support\Erp\Dre;

/**
 * Catálogo de classificações DRE sugeridas para planos padrão do UNI ERP.
 * Casa por descrição normalizada (sinônimos). Não depende só do código numérico.
 * Planos ambíguos retornam null (sem sugestão automática).
 */
final class DrePlanoClassificacaoSugestoes
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    private const SUGESTOES = [
        ['TAXA DE CARTAO', DrePlanoClassificacaoCatalog::DESPESA_FINANCEIRA],
        ['ABERTURA DE CAIXA', DrePlanoClassificacaoCatalog::PATRIMONIAL],
        ['COMPRA DE MERCADORIA', DrePlanoClassificacaoCatalog::PATRIMONIAL],
        ['VENDA DE MERCADORIA', DrePlanoClassificacaoCatalog::RECEITA],
        ['VENDAS DE MERCADORIA', DrePlanoClassificacaoCatalog::RECEITA],
        ['RECEBIMENTO DE CONTA', DrePlanoClassificacaoCatalog::PATRIMONIAL],
        ['RECEBIMENTODE CONTA', DrePlanoClassificacaoCatalog::PATRIMONIAL],
        ['RECEBIMENTO DE CLIENTE', DrePlanoClassificacaoCatalog::PATRIMONIAL],
        ['TRANSFERENCIA', DrePlanoClassificacaoCatalog::PATRIMONIAL],
        ['FORNECEDOR', DrePlanoClassificacaoCatalog::PATRIMONIAL],
        ['ENERGIA', DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL],
        ['FUNCIONARIO', DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL],
        ['SALARIO', DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL],
        ['PRO LABORE', DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL],
        ['PROLABORE', DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL],
        ['AGUA', DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL],
        ['CONTADOR', DrePlanoClassificacaoCatalog::DESPESA_ADMINISTRATIVA],
    ];

    /** @var list<string> */
    private const AMBIGUOS = [
        'IMPOSTO',
        'IMPOSTOS',
        'IMPOSTOS TAXAS',
        'RETIRADA DE CAIXA',
        'RETIRADA',
        'DEVOLUCAO DE MERCADORIA',
        'DEVOLUCAO',
        'BOLETO BANCARIO',
        'BOLETO',
    ];

    public static function normalizarDescricao(?string $descricao): string
    {
        $s = mb_strtoupper(trim((string) $descricao), 'UTF-8');
        if ($s === '') {
            return '';
        }

        $map = [
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A',
            'É' => 'E', 'Ê' => 'E',
            'Í' => 'I',
            'Ó' => 'O', 'Õ' => 'O', 'Ô' => 'O',
            'Ú' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^A-Z0-9]+/', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return trim($s);
    }

    /**
     * @param  array{id?: int, codigo?: int|string, descricao?: string}  $plano
     */
    public static function sugerir(array $plano): ?string
    {
        $norm = self::normalizarDescricao($plano['descricao'] ?? null);
        if ($norm === '') {
            return null;
        }

        foreach (self::AMBIGUOS as $amb) {
            if ($norm === $amb || str_starts_with($norm, $amb.' ')) {
                return null;
            }
        }

        foreach (self::SUGESTOES as [$needle, $classe]) {
            if ($norm === $needle || str_starts_with($norm, $needle) || str_contains($norm, $needle)) {
                return $classe;
            }
        }

        return null;
    }
}
