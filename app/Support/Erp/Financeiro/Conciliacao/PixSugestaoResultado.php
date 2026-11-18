<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

/**
 * Resultado do motor de sugestões PIX (C4B).
 * Não altera extrato nem financeiro.
 */
final class PixSugestaoResultado
{
    public const CLASS_FORTE = 'forte';

    public const CLASS_POSSIVEL = 'possivel';

    public const CLASS_AMBIGUA = 'ambigua';

    public const CLASS_SEM = 'sem_correspondencia';

    /**
     * @param  list<PixSugestaoCandidato>  $candidatos
     * @param  list<string>  $avisos
     */
    public function __construct(
        public readonly int $extratoId,
        public readonly string $classificacao,
        public readonly array $candidatos,
        public readonly array $avisos = [],
        public readonly bool $extratoParecePix = false,
    ) {
    }

    /**
     * @return array{
     *   extrato_id: int,
     *   classificacao: string,
     *   extrato_parece_pix: bool,
     *   total_candidatos: int,
     *   avisos: list<string>,
     *   candidatos: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'extrato_id' => $this->extratoId,
            'classificacao' => $this->classificacao,
            'extrato_parece_pix' => $this->extratoParecePix,
            'total_candidatos' => count($this->candidatos),
            'avisos' => array_values($this->avisos),
            'candidatos' => array_map(
                static fn (PixSugestaoCandidato $c): array => $c->toArray(),
                $this->candidatos,
            ),
        ];
    }
}
