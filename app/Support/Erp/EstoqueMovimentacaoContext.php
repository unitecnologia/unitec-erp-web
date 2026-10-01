<?php

namespace App\Support\Erp;

use App\Models\EstoqueMovimentacao;

/**
 * Metadados opcionais do extrato de estoque — não altera o cálculo do saldo.
 */
final class EstoqueMovimentacaoContext
{
    public function __construct(
        public readonly string $tipo = EstoqueMovimentacao::TIPO_SISTEMA,
        public readonly ?int $empresaId = null,
        public readonly ?string $origemTipo = null,
        public readonly ?int $origemId = null,
        public readonly ?string $origemNumero = null,
        public readonly ?string $docFiscalTipo = null,
        public readonly ?string $docFiscalNumero = null,
        public readonly ?int $usuarioId = null,
        public readonly ?string $observacao = null,
    ) {}

    public static function make(
        string $tipo,
        ?int $empresaId = null,
        ?string $origemTipo = null,
        ?int $origemId = null,
        ?string $origemNumero = null,
        ?string $docFiscalTipo = null,
        ?string $docFiscalNumero = null,
        ?int $usuarioId = null,
        ?string $observacao = null,
    ): self {
        $numero = $origemNumero !== null ? trim((string) $origemNumero) : null;
        $fiscalNumero = $docFiscalNumero !== null ? trim((string) $docFiscalNumero) : null;
        $fiscalTipo = $docFiscalTipo !== null ? trim((string) $docFiscalTipo) : null;

        return new self(
            tipo: $tipo,
            empresaId: $empresaId,
            origemTipo: $origemTipo,
            origemId: $origemId,
            origemNumero: $numero !== '' ? $numero : null,
            docFiscalTipo: $fiscalTipo !== '' ? $fiscalTipo : null,
            docFiscalNumero: $fiscalNumero !== '' ? $fiscalNumero : null,
            usuarioId: $usuarioId,
            observacao: $observacao,
        );
    }
}
