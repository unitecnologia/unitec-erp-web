<?php



namespace Unitec\FiscalEngine\Dto;



final class InutilizarNfeResponse

{

    public function __construct(

        public readonly bool $inutilizada,

        public readonly string $protocolo,

        public readonly string $xml,

        public readonly string $statusCodigo,

        public readonly string $statusMotivo,

        public readonly int $numeroInicial,

        public readonly int $numeroFinal,

        public readonly int $serie,

    ) {}

}

