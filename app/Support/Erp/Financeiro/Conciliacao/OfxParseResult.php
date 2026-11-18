<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

/**
 * Resultado do parsing OFX (C2) — sem I/O de banco.
 *
 * @phpstan-type Tx OfxParsedTransaction
 */
final class OfxParseResult
{
    /**
     * @param  list<OfxParsedTransaction>  $transactions
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly string $encoding,
        public readonly string $dialect,
        public readonly ?string $bankId,
        public readonly ?string $branchId,
        public readonly ?string $accountId,
        public readonly ?string $periodoInicio,
        public readonly ?string $periodoFim,
        public readonly array $transactions,
        public readonly array $warnings = [],
    ) {}

    public function totalCreditos(): string
    {
        $sum = 0.0;
        foreach ($this->transactions as $tx) {
            if ($tx->tipo === 'credito') {
                $sum += (float) $tx->valor;
            }
        }

        return number_format($sum, 2, '.', '');
    }

    public function totalDebitos(): string
    {
        $sum = 0.0;
        foreach ($this->transactions as $tx) {
            if ($tx->tipo === 'debito') {
                $sum += (float) $tx->valor;
            }
        }

        return number_format($sum, 2, '.', '');
    }
}
