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

        $this->exigirCertificado($certificate);

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
            mtls: $this->mtls($certificate),
        );
    }

    public function prepararEvento(string $chaveAcesso, string $xmlAssinado, Certificate $certificate): NfseSefinRequisicao
    {
        if (trim($xmlAssinado) === '') {
            throw new NfseNaoTransmitida('XML assinado do pedido de evento ausente.');
        }

        $this->exigirCertificado($certificate);

        $payload = json_encode([
            'pedidoRegistroEventoXmlGZipB64' => base64_encode(gzencode($xmlAssinado)),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return new NfseSefinRequisicao(
            url: NfseSefinEndpoints::eventos($this->ambiente, $chaveAcesso),
            metodo: 'POST',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            corpo: $payload,
            mtls: $this->mtls($certificate),
        );
    }

    public function prepararConsultaEvento(
        string $chaveAcesso,
        string $tipoEvento,
        string $numSeqEvento,
        Certificate $certificate,
    ): NfseSefinRequisicao {
        $this->exigirCertificado($certificate);

        return new NfseSefinRequisicao(
            url: NfseSefinEndpoints::evento($this->ambiente, $chaveAcesso, $tipoEvento, $numSeqEvento),
            metodo: 'GET',
            headers: [
                'Accept' => 'application/json',
            ],
            corpo: '',
            mtls: $this->mtls($certificate),
        );
    }

    private function exigirCertificado(Certificate $certificate): void
    {
        if ($certificate->certificatePem === '' || $certificate->privateKeyPem === '') {
            throw new NfseSefinNaoEnviada('Certificado ou chave privada da empresa ausente.');
        }
    }

    /**
     * @return array{certificado: string, chave_privada: string}
     */
    private function mtls(Certificate $certificate): array
    {
        return [
            'certificado' => $certificate->certificatePem,
            'chave_privada' => $certificate->privateKeyPem,
        ];
    }

    public function enviar(string $xmlAssinado, Certificate $certificate, NfseSefinTransporte $transporte): void
    {
        if (! $this->ambiente->eTeste()) {
            throw new NfseSefinNaoEnviada('O host de Produção da SEFIN Nacional não está habilitado.');
        }

        $transporte->enviar($this->preparar($xmlAssinado, $certificate));
    }
}
