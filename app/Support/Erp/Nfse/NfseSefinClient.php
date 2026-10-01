<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use Unitec\FiscalEngine\Certificate\Certificate;

class NfseSefinClient
{
    public function __construct(
        private readonly NfseSefinAmbiente $ambiente = NfseSefinAmbiente::ProducaoRestrita,
    ) {}

    public static function daEmpresa(Empresa $empresa): self
    {
        return new self(NfseSefinAmbiente::daEmpresa($empresa->nfse_ambiente));
    }

    public function ambiente(): NfseSefinAmbiente
    {
        return $this->ambiente;
    }

    public function url(): string
    {
        return $this->ambiente->url();
    }

    public function preparar(string $xmlAssinado, Certificate $certificate): NfseSefinRequisicao
    {
        if (trim($xmlAssinado) === '') {
            throw new NfseSefinNaoEnviada('XML assinado da DPS ausente.');
        }

        if ($certificate->certificatePem === '' || $certificate->privateKeyPem === '') {
            throw new NfseSefinNaoEnviada('Certificado ou chave privada da empresa ausente.');
        }

        $payload = json_encode([
            'dpsXmlGZipB64' => base64_encode(gzencode($xmlAssinado)),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return new NfseSefinRequisicao(
            url: $this->url(),
            metodo: 'POST',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            corpo: $payload,
            mtls: [
                'certificado' => $certificate->certificatePem,
                'chave_privada' => $certificate->privateKeyPem,
            ],
        );
    }

    public function enviar(string $xmlAssinado, Certificate $certificate, NfseSefinTransporte $transporte): void
    {
        if (! $this->ambiente->eTeste()) {
            throw new NfseSefinNaoEnviada('O host de Produção da SEFIN Nacional não está habilitado.');
        }

        $transporte->enviar($this->preparar($xmlAssinado, $certificate));
    }
}
