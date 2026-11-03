<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaLancamento;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Identificação estável de transferência entre contas (sem coluna nova).
 * Documento: TRF-{token} (mesmo nos dois lados). Histórico: [TRANSFERENCIA] …
 */
final class CaixaTransferencia
{
    public const DOCUMENTO_PREFIXO = 'TRF-';

    public const HISTORICO_PREFIXO = '[TRANSFERENCIA]';

    public static function isDocumentoTransferencia(?string $documento): bool
    {
        $documento = trim((string) $documento);

        return $documento !== ''
            && str_starts_with(mb_strtoupper($documento, 'UTF-8'), self::DOCUMENTO_PREFIXO);
    }

    public static function isLancamentoTransferencia(?CaixaLancamento $lancamento): bool
    {
        if ($lancamento === null) {
            return false;
        }

        return self::isDocumentoTransferencia($lancamento->documento);
    }

    public static function novoDocumento(): string
    {
        // Cabe em varchar(40): TRF- + 16 hex = 20 chars.
        return self::DOCUMENTO_PREFIXO.strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * Extrato/saldo: inclui transferências.
     * Totais de “movimento operacional” / balanço de resultado: exclui.
     */
    public static function excluirDoQuery(EloquentBuilder|QueryBuilder $query, string $documentoColumn = 'documento'): void
    {
        $query->where(function ($builder) use ($documentoColumn): void {
            $builder
                ->whereNull($documentoColumn)
                ->orWhere($documentoColumn, '')
                ->orWhere($documentoColumn, 'not like', self::DOCUMENTO_PREFIXO.'%');
        });
    }
}
