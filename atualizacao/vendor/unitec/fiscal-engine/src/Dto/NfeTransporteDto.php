<?php

namespace Unitec\FiscalEngine\Dto;

/**
 * Detalhes de transporte da NF-e (grupo transp além do modFrete).
 */
final class NfeTransporteDto
{
    public function __construct(
        public readonly ?string $transportadoraDocumento = null,
        public readonly ?string $transportadoraNome = null,
        public readonly ?string $transportadoraIe = null,
        public readonly ?string $transportadoraEndereco = null,
        public readonly ?string $transportadoraMunicipio = null,
        public readonly ?string $transportadoraUf = null,
        public readonly ?string $placa = null,
        public readonly ?string $ufPlaca = null,
        public readonly ?int $qVol = null,
        public readonly ?string $esp = null,
        public readonly ?string $marca = null,
        public readonly ?string $nVol = null,
        public readonly ?float $pesoL = null,
        public readonly ?float $pesoB = null,
    ) {}

    public function hasTransportadora(): bool
    {
        return $this->nonEmpty($this->transportadoraDocumento) || $this->nonEmpty($this->transportadoraNome);
    }

    public function hasVeiculo(): bool
    {
        return $this->nonEmpty($this->placa) && $this->nonEmpty($this->ufPlaca);
    }

    public function hasVolume(): bool
    {
        return ($this->qVol !== null && $this->qVol > 0)
            || $this->nonEmpty($this->esp)
            || $this->nonEmpty($this->marca)
            || $this->nonEmpty($this->nVol)
            || ($this->pesoL !== null && $this->pesoL > 0)
            || ($this->pesoB !== null && $this->pesoB > 0);
    }

    private function nonEmpty(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
