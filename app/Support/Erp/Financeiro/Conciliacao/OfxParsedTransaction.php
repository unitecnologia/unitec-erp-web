<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

/**
 * Linha STMTTRN já normalizada pelo parser (C2).
 */
final class OfxParsedTransaction
{
    public function __construct(
        public readonly string $dataMovimento,
        public readonly ?string $dataPosted,
        public readonly string $tipo,
        public readonly string $valor,
        public readonly ?string $historico,
        public readonly ?string $memo,
        public readonly ?string $payee,
        public readonly ?string $checkNum,
        public readonly ?string $fitid,
        public readonly ?string $refNum,
        public readonly int $sequenciaArquivo,
        public readonly bool $ambiguo,
    ) {}
}
