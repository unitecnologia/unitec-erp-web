<?php

namespace App\Support\Erp\Tef;

use App\Models\TefLiquidacaoDestino;

/**
 * Resultado determinístico da resolução do destino previsto (C5B.1).
 * Nunca implica liquidação financeira.
 */
final class TefLiquidacaoDestinoResolveResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_NAO_ENCONTRADO = 'nao_encontrado';

    public const STATUS_AMBIGUO = 'ambiguo';

    public const STATUS_CONTA_INVALIDA = 'conta_invalida';

    /**
     * @param  list<TefLiquidacaoDestino>  $candidatos
     */
    public function __construct(
        public readonly string $status,
        public readonly ?TefLiquidacaoDestino $destino = null,
        public readonly array $candidatos = [],
        public readonly ?string $motivo = null,
    ) {
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK && $this->destino !== null;
    }
}
