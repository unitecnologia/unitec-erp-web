<?php

namespace Unitec\FiscalEngine;

use Unitec\FiscalEngine\Dto\CancelarNfceRequest;
use Unitec\FiscalEngine\Dto\CancelarNfceResponse;
use Unitec\FiscalEngine\Dto\CartaCorrecaoNfeRequest;
use Unitec\FiscalEngine\Dto\CartaCorrecaoNfeResponse;
use Unitec\FiscalEngine\Dto\ConsultarDistribuicaoDfeRequest;
use Unitec\FiscalEngine\Dto\ConsultarDistribuicaoDfeResponse;
use Unitec\FiscalEngine\Dto\ConsultarNfceRequest;
use Unitec\FiscalEngine\Dto\ConsultarNfceResponse;
use Unitec\FiscalEngine\Dto\EmitirNfeRequest;
use Unitec\FiscalEngine\Dto\EmitirNfeResponse;
use Unitec\FiscalEngine\Dto\EmitirNfceRequest;
use Unitec\FiscalEngine\Dto\EmitirNfceResponse;
use Unitec\FiscalEngine\Dto\InutilizarNfceRequest;
use Unitec\FiscalEngine\Dto\InutilizarNfceResponse;
use Unitec\FiscalEngine\Dto\InutilizarNfeRequest;
use Unitec\FiscalEngine\Dto\InutilizarNfeResponse;
use Unitec\FiscalEngine\Nfce\NfceCanceller;
use Unitec\FiscalEngine\Nfce\NfceConsultor;
use Unitec\FiscalEngine\Nfce\NfceEmitter;
use Unitec\FiscalEngine\Nfce\NfceInutilizador;
use Unitec\FiscalEngine\Nfe\DfeDistribuidor;
use Unitec\FiscalEngine\Nfe\NfeCanceller;
use Unitec\FiscalEngine\Nfe\NfeCartaCorrecaoEmitter;
use Unitec\FiscalEngine\Nfe\NfeConsultor;
use Unitec\FiscalEngine\Nfe\NfeEmitter;
use Unitec\FiscalEngine\Nfe\NfeInutilizador;

final class FiscalEngine
{
    public function __construct(
        private readonly NfceEmitter $nfceEmitter = new NfceEmitter(),
        private readonly NfceCanceller $nfceCanceller = new NfceCanceller(),
        private readonly NfceConsultor $nfceConsultor = new NfceConsultor(),
        private readonly NfceInutilizador $nfceInutilizador = new NfceInutilizador(),
        private readonly NfeEmitter $nfeEmitter = new NfeEmitter(),
        private readonly NfeCanceller $nfeCanceller = new NfeCanceller(),
        private readonly NfeCartaCorrecaoEmitter $nfeCartaCorrecaoEmitter = new NfeCartaCorrecaoEmitter(),
        private readonly NfeInutilizador $nfeInutilizador = new NfeInutilizador(),
        private readonly NfeConsultor $nfeConsultor = new NfeConsultor(),
        private readonly DfeDistribuidor $dfeDistribuidor = new DfeDistribuidor(),
    ) {}

    public function emitirNfce(EmitirNfceRequest $request): EmitirNfceResponse
    {
        return $this->nfceEmitter->emitir($request);
    }

    public function prepararNfceContingencia(EmitirNfceRequest $request): EmitirNfceResponse
    {
        return $this->nfceEmitter->prepararContingencia($request);
    }

    /**
     * @return array{nfeXml: string, chave: string, qrUrl: string, enviNfe: string}
     */
    public function prepararNfceAssinada(EmitirNfceRequest $request): array
    {
        return $this->nfceEmitter->prepararAssinada($request);
    }

    public function autorizarNfceAssinada(
        string $nfeXml,
        \Unitec\FiscalEngine\Certificate\Certificate $certificate,
        int $tpAmb,
        string $chave,
        string $qrCodeUrl,
        int $numero,
        int $serie,
        int $cNf,
    ): EmitirNfceResponse {
        return $this->nfceEmitter->autorizarNfeAssinada(
            $nfeXml,
            $certificate,
            $tpAmb,
            $chave,
            $qrCodeUrl,
            $numero,
            $serie,
            $cNf,
        );
    }

    public function cancelarNfce(CancelarNfceRequest $request): CancelarNfceResponse
    {
        return $this->nfceCanceller->cancelar($request);
    }

    public function consultarNfce(ConsultarNfceRequest $request): ConsultarNfceResponse
    {
        return $this->nfceConsultor->consultar($request);
    }

    public function consultarNfe(ConsultarNfceRequest $request): ConsultarNfceResponse
    {
        return $this->nfeConsultor->consultar($request);
    }

    public function inutilizarNfce(InutilizarNfceRequest $request): InutilizarNfceResponse
    {
        return $this->nfceInutilizador->inutilizar($request);
    }

    public function emitirNfe(EmitirNfeRequest $request): EmitirNfeResponse
    {
        return $this->nfeEmitter->emitir($request);
    }

    public function cancelarNfe(CancelarNfceRequest $request): CancelarNfceResponse
    {
        return $this->nfeCanceller->cancelar($request);
    }

    public function emitirCartaCorrecaoNfe(CartaCorrecaoNfeRequest $request): CartaCorrecaoNfeResponse
    {
        return $this->nfeCartaCorrecaoEmitter->emitir($request);
    }

    public function inutilizarNfe(InutilizarNfeRequest $request): InutilizarNfeResponse
    {
        return $this->nfeInutilizador->inutilizar($request);
    }

    public function consultarDistribuicaoDfe(ConsultarDistribuicaoDfeRequest $request): ConsultarDistribuicaoDfeResponse
    {
        return $this->dfeDistribuidor->consultar($request);
    }
}
