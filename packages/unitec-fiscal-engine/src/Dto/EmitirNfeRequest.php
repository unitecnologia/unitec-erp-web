<?php

namespace Unitec\FiscalEngine\Dto;

use Unitec\FiscalEngine\Certificate\Certificate;

final class EmitirNfeRequest
{
    /**
     * @param  list<ItemDto>  $itens
     * @param  list<FaturaParcelaDto>  $parcelas
     */
    public function __construct(
        public readonly Certificate $certificate,
        public readonly EmitenteDto $emitente,
        public readonly IdeDto $ide,
        public readonly NfeDestinatarioDto $destinatario,
        public readonly array $itens,
        public readonly float $valorProdutos,
        public readonly float $valorNota,
        public readonly int $idDest,
        public readonly int $indFinal,
        public readonly int $finNFe,
        public readonly int $modFrete,
        public readonly float $valorDesconto = 0.0,
        public readonly float $valorFrete = 0.0,
        public readonly float $valorSeguro = 0.0,
        public readonly float $valorOutros = 0.0,
        public readonly float $valorTotTrib = 0.0,
        public readonly ?\DateTimeInterface $dataSaida = null,
        public readonly array $parcelas = [],
        public readonly string $informacoesComplementares = '',
        public readonly string $informacoesFisco = '',
        /** @var list<PagamentoDto> */
        public readonly array $pagamentos = [],
        public readonly bool $homologacao = false,
        public readonly ?RespTecnicoDto $respTecnico = null,
        public readonly ?NfeTransporteDto $transporte = null,
        /** @var list<string> Chaves NF-e referenciadas (44 dígitos) — obrigatório quando finNFe = 4. */
        public readonly array $chavesReferenciadas = [],
    ) {}
}
