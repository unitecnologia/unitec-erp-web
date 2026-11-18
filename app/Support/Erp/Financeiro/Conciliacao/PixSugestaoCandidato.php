<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

/**
 * Candidato de sugestão PIX (C4B) — somente leitura / DTO.
 *
 * @phpstan-type Evidencia array<string, scalar|null>
 */
final class PixSugestaoCandidato
{
    public const ORIGEM_CAIXA = 'caixa_lancamento';

    public const ORIGEM_PIX_COBRANCA = 'pix_cobranca';

    public const ORIGEM_CR_PAGAMENTO = 'conta_receber_pagamento';

    public const ORIGEM_CP_PAGAMENTO = 'conta_pagar_pagamento';

    public const ORIGEM_PDV_PAGAMENTO = 'pdv_venda_pagamento';

    /**
     * @param  array<string, scalar|null>  $evidencias
     */
    public function __construct(
        public readonly string $origem,
        public readonly int $origemId,
        public readonly string $classificacao,
        public readonly float $valor,
        public readonly ?string $data,
        public readonly ?int $caixaContaId,
        public readonly string $resumo,
        public readonly array $evidencias = [],
    ) {
    }

    /**
     * @return array{
     *   origem: string,
     *   origem_id: int,
     *   classificacao: string,
     *   valor: float,
     *   data: ?string,
     *   caixa_conta_id: ?int,
     *   resumo: string,
     *   evidencias: array<string, scalar|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'origem' => $this->origem,
            'origem_id' => $this->origemId,
            'classificacao' => $this->classificacao,
            'valor' => round($this->valor, 2),
            'data' => $this->data,
            'caixa_conta_id' => $this->caixaContaId,
            'resumo' => $this->resumo,
            'evidencias' => $this->evidencias,
        ];
    }
}
