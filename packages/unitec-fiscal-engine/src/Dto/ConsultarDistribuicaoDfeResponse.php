<?php

namespace Unitec\FiscalEngine\Dto;

final class ConsultarDistribuicaoDfeResponse
{
    /**
     * @param  list<DfeResumoNfe>  $documentos
     */
    public function __construct(
        public readonly string $statusCodigo,
        public readonly string $statusMotivo,
        public readonly string $ultNsu,
        public readonly string $maxNsu,
        public readonly array $documentos,
        public readonly string $xml,
    ) {}

    public function temDocumentos(): bool
    {
        return $this->statusCodigo === '137' || $this->documentos !== [];
    }

    public function possuiMaisDocumentos(): bool
    {
        return $this->ultNsu !== '' && $this->maxNsu !== '' && $this->ultNsu < $this->maxNsu;
    }
}
