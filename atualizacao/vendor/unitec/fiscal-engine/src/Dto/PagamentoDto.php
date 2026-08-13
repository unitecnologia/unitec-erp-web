<?php

namespace Unitec\FiscalEngine\Dto;

final class PagamentoDto
{
    public function __construct(
        public readonly string $tipo,
        public readonly float $valor,
        /** tpIntegra: 1=TEF integrado, 2=POS não integrado */
        public readonly ?string $tpIntegra = null,
        public readonly ?string $tBand = null,
        public readonly ?string $cAut = null,
        public readonly ?string $cnpjCredenciadora = null,
        /** xPag: obrigatório quando tPag=99 */
        public readonly ?string $descricao = null,
    ) {}

    public function isCartao(): bool
    {
        $tipo = str_pad($this->tipo, 2, '0', STR_PAD_LEFT);

        return in_array($tipo, ['03', '04'], true);
    }

    public function isOutros(): bool
    {
        return str_pad($this->tipo, 2, '0', STR_PAD_LEFT) === '99';
    }
}
