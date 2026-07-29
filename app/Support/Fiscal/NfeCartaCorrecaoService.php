<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeCartaCorrecao;
use App\Models\NfeEvento;
use App\Models\VendasParametro;
use App\Support\Erp\Nfe\NfeCartaCorrecaoMotivo;
use App\Support\Erp\Nfe\NfeEventoLogger;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use Unitec\FiscalEngine\Certificate\CertificateLoader;
use Unitec\FiscalEngine\Dto\CartaCorrecaoNfeRequest;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;
use Unitec\FiscalEngine\Util\CaBundleResolver;

final class NfeCartaCorrecaoService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly FiscalEngine $engine = new FiscalEngine(),
    ) {}

    public function emitir(Nfe $nfe, Empresa $empresa, string $correcao): NfeCartaCorrecao
    {
        if ($nfe->status === Nfe::STATUS_CANCELADA) {
            throw new FiscalEngineException('NF-e cancelada não pode receber Carta de Correção.');
        }

        if ($nfe->status !== Nfe::STATUS_TRANSMITIDA) {
            throw new FiscalEngineException('Somente NF-e transmitida pode receber Carta de Correção.');
        }

        if (blank($nfe->chave)) {
            throw new FiscalEngineException('NF-e sem chave de acesso para Carta de Correção.');
        }

        $correcao = NfeCartaCorrecaoMotivo::normalize($correcao);
        $erroMotivo = NfeCartaCorrecaoMotivo::validate($correcao);

        if ($erroMotivo !== null) {
            throw new FiscalEngineException($erroMotivo);
        }

        $sequencia = $this->proximaSequencia($nfe);

        if ($sequencia > NfeCartaCorrecaoMotivo::MAX_SEQUENCIA) {
            throw new FiscalEngineException('Limite de 20 Cartas de Correção atingido para esta NF-e.');
        }

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->payloadBuilder->podeCancelarReal($parametros, $empresa)) {
            throw new FiscalEngineException('Carta de Correção real de NF-e não está configurada para esta empresa/UF.');
        }

        CaBundleResolver::setProjectRoot(base_path());

        $certPath = NfeFiscalConfig::certificadoAbsolutePath($parametros);
        $senha = $parametros->safeSenhaCertificado();

        if ($certPath === null || $senha === null) {
            throw new FiscalEngineException('Certificado digital ou senha não configurados.');
        }

        $certificate = CertificateLoader::fromPkcs12File($certPath, $senha, (string) $empresa->cnpj);
        $tpAmb = $parametros->ambiente === VendasParametro::AMBIENTE_PRODUCAO ? 1 : 2;

        $request = new CartaCorrecaoNfeRequest(
            certificate: $certificate,
            cnpj: (string) $empresa->cnpj,
            chave: (string) $nfe->chave,
            correcao: $correcao,
            tpAmb: $tpAmb,
            nSeqEvento: $sequencia,
        );

        $response = $this->engine->emitirCartaCorrecaoNfe($request);

        return tap(NfeCartaCorrecao::query()->create([
            'nfe_id' => $nfe->id,
            'sequencia' => $sequencia,
            'correcao' => $correcao,
            'protocolo' => $response->protocoloEvento !== '' ? $response->protocoloEvento : null,
            'xml' => $response->xml,
        ]), function (NfeCartaCorrecao $carta) use ($correcao, $response): void {
            NfeEventoLogger::registrar(
                nfeId: (int) $carta->nfe_id,
                tipo: NfeEvento::TIPO_CARTA_CORRECAO,
                titulo: 'Carta de Correção nº ' . $carta->sequencia,
                descricao: trim(mb_substr($correcao, 0, 500) . (filled($response->protocoloEvento) ? ' Protocolo: ' . $response->protocoloEvento . '.' : '')),
                referenciaTipo: NfeCartaCorrecao::class,
                referenciaId: (int) $carta->id,
            );
        });
    }

    private function proximaSequencia(Nfe $nfe): int
    {
        $ultima = NfeCartaCorrecao::query()
            ->where('nfe_id', $nfe->id)
            ->max('sequencia');

        return ((int) $ultima) + 1;
    }
}
