<?php



namespace App\Support\Fiscal;



use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeEvento;
use App\Models\VendasParametro;

use App\Support\Erp\Nfe\NfeFiscalConfig;

use App\Support\Erp\Nfe\NfeEventoLogger;

use App\Support\Erp\Nfe\NfeInutilizacaoMotivo;

use Unitec\FiscalEngine\Certificate\CertificateLoader;

use Unitec\FiscalEngine\Dto\InutilizarNfeRequest;

use Unitec\FiscalEngine\Dto\InutilizarNfeResponse;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

use Unitec\FiscalEngine\FiscalEngine;

use Unitec\FiscalEngine\Util\CaBundleResolver;



final class NfeInutilizacaoService

{

    public function __construct(

        private readonly NfeFiscalPayloadBuilder $payloadBuilder = new NfeFiscalPayloadBuilder(),

        private readonly FiscalEngine $engine = new FiscalEngine(),

    ) {}



    public function inutilizar(

        Empresa $empresa,

        int $serie,

        int $numeroInicial,

        int $numeroFinal,

        string $justificativa,

    ): InutilizarNfeResponse {

        $justificativa = NfeInutilizacaoMotivo::normalize($justificativa);

        $erroMotivo = NfeInutilizacaoMotivo::validate($justificativa);



        if ($erroMotivo !== null) {

            throw new FiscalEngineException($erroMotivo);

        }



        if ($numeroInicial < 1 || $numeroFinal < $numeroInicial) {

            throw new FiscalEngineException('Faixa de numeração inválida para inutilização.');

        }



        $parametros = VendasParametro::forEmpresa((int) $empresa->id);



        if (! $this->payloadBuilder->podeEmitirReal($parametros, $empresa)) {

            throw new FiscalEngineException('Inutilização real de NF-e não está configurada para esta empresa/UF.');

        }



        CaBundleResolver::setProjectRoot(base_path());



        $certPath = NfeFiscalConfig::certificadoAbsolutePath($parametros);

        $senha = $parametros->safeSenhaCertificado();



        if ($certPath === null || $senha === null) {

            throw new FiscalEngineException('Certificado digital ou senha não configurados.');

        }



        $certificate = CertificateLoader::fromPkcs12File($certPath, $senha, (string) $empresa->cnpj);

        $tpAmb = $parametros->ambiente === VendasParametro::AMBIENTE_PRODUCAO ? 1 : 2;



        return $this->engine->inutilizarNfe(new InutilizarNfeRequest(
            certificate: $certificate,
            cnpj: (string) $empresa->cnpj,
            tpAmb: $tpAmb,
            serie: $serie,
            numeroInicial: $numeroInicial,
            numeroFinal: $numeroFinal,
            justificativa: $justificativa,
        ));
    }

    public function marcarNotasLocaisInutilizadas(
        Empresa $empresa,
        int $serie,
        int $numeroInicial,
        int $numeroFinal,
    ): int {
        $serieNormalizada = (string) (int) ltrim((string) $serie, '0') ?: 1;
        $atualizadas = 0;

        $candidatas = Nfe::query()
            ->where('empresa_id', $empresa->id)
            ->get();

        foreach ($candidatas as $nfe) {
            if (! $this->nfePertenceFaixaInutilizacao($nfe, $serieNormalizada, $numeroInicial, $numeroFinal)) {
                continue;
            }

            if ($nfe->status !== Nfe::STATUS_ABERTA) {
                continue;
            }

            $nfe->update([
                'status' => Nfe::STATUS_INUTILIZADA,
                'situacao' => Nfe::SITUACAO_INUTILIZADA,
            ]);

            NfeEventoLogger::registrar(
                nfeId: (int) $nfe->id,
                tipo: NfeEvento::TIPO_INUTILIZADA,
                titulo: 'Numeração inutilizada',
                descricao: 'Série ' . $serieNormalizada . ', faixa ' . $numeroInicial . ' a ' . $numeroFinal . '.',
            );

            $atualizadas++;
        }

        return $atualizadas;
    }

    private function nfePertenceFaixaInutilizacao(Nfe $nfe, string $serie, int $numeroInicial, int $numeroFinal): bool
    {
        $serieNota = (string) (int) ltrim((string) ($nfe->serie ?? '1'), '0') ?: 1;

        if ($serieNota !== $serie) {
            return false;
        }

        $numeroNota = (int) (ltrim(preg_replace('/\D/', '', (string) $nfe->numero) ?? '', '0') ?: '0');

        return $numeroNota >= $numeroInicial && $numeroNota <= $numeroFinal;
    }
}

