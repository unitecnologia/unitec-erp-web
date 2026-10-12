<?php

namespace App\Support\Mesas;

use RuntimeException;

/**
 * Lançamento recusado sem gravar nada. `retry` = o app reenvia sozinho mais tarde;
 * sem retry o garçom precisa decidir (confirmar, ajustar ou descartar).
 */
final class MesasConflito extends RuntimeException
{
    public const MESA_OCUPADA = 'MESA_OCUPADA';

    public const MESA_AGUARDANDO = 'MESA_AGUARDANDO';

    public const MESA_FECHADA = 'MESA_FECHADA';

    public const PRECO_ALTERADO = 'PRECO_ALTERADO';

    public const PRODUTO_INDISPONIVEL = 'PRODUTO_INDISPONIVEL';

    /**
     * @param  array<string, mixed>  $detalhes
     */
    public function __construct(
        public readonly string $codigo,
        string $mensagem,
        public readonly bool $retry = false,
        public readonly array $detalhes = [],
    ) {
        parent::__construct($mensagem);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => false,
            'code' => $this->codigo,
            'message' => $this->getMessage(),
            'retry' => $this->retry,
            'detalhes' => $this->detalhes,
        ];
    }
}
