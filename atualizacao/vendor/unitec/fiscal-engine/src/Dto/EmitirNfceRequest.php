<?php

namespace Unitec\FiscalEngine\Dto;

use Unitec\FiscalEngine\Certificate\Certificate;

final class EmitirNfceRequest
{
    /**
     * @param  list<ItemDto>  $itens
     * @param  list<PagamentoDto>  $pagamentos
     */
    public function __construct(
        public readonly Certificate $certificate,
        public readonly EmitenteDto $emitente,
        public readonly IdeDto $ide,
        public readonly array $itens,
        public readonly array $pagamentos,
        public readonly float $valorProdutos,
        public readonly float $valorNota,
        public readonly float $valorDesconto = 0.0,
        public readonly float $valorAcrescimo = 0.0,
        public readonly float $valorTotTrib = 0.0,
        public readonly ?DestinatarioDto $destinatario = null,
        public readonly string $idToken = '',
        public readonly string $csc = '',
        public readonly int $versaoQrcode = 2,
        public readonly string $informacoesComplementares = '',
        public readonly bool $homologacao = false,
        public readonly ?RespTecnicoDto $respTecnico = null,
    ) {}
}
