<?php

namespace App\Support\Fiscal;

use App\Jobs\ContadorCloudProcessPendingJob;
use App\Models\Empresa;
use App\Models\VendasParametro;
use Unitec\FiscalEngine\Dto\ConsultarDistribuicaoDfeRequest;
use Unitec\FiscalEngine\Dto\DfeResumoNfe;
use Unitec\FiscalEngine\FiscalEngine;
use Unitec\FiscalEngine\Nfe\DfeDistribuidor;

final class DistribuicaoDfeService
{
    private const MAX_LOTES_POR_CONSULTA = 12;

    public function __construct(
        private readonly FiscalEngine $engine = new FiscalEngine(),
        private readonly NotaFornecedorImportService $importService = new NotaFornecedorImportService(),
    ) {}

    /**
     * @return array{importadas: int, atualizadas: int, documentos: int, ultimo_nsu: string, mensagem: string, cstat: string}
     */
    public function consultarLote(Empresa $empresa, ?VendasParametro $parametros = null): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $parametros ??= VendasParametro::forEmpresa((int) $empresa->id);
        DistribuicaoDfeConfig::validarConsulta($parametros, $empresa);

        $certificate = NfceFiscalCertificateResolver::resolve($empresa, $parametros);
        $tpAmb = NfceFiscalCertificateResolver::tpAmb($parametros);
        $cUfAutor = DistribuicaoDfeConfig::cUfAutor($empresa, $parametros);
        $cnpj = (string) $empresa->cnpj;

        $ultNsu = DistribuicaoDfeConfig::ultimoNsu($parametros);
        $importadas = 0;
        $atualizadas = 0;
        $documentos = 0;
        $iteracoes = 0;
        $ultimoCstat = DistribuicaoDfeMensagens::CSTAT_SEM_DOCUMENTOS;
        $ultimaMensagem = DistribuicaoDfeMensagens::semDocumentosNovos();

        while ($iteracoes < self::MAX_LOTES_POR_CONSULTA) {
            $iteracoes++;

            $response = $this->engine->consultarDistribuicaoDfe(new ConsultarDistribuicaoDfeRequest(
                certificate: $certificate,
                cnpj: $cnpj,
                cUfAutor: $cUfAutor,
                tpAmb: $tpAmb,
                ultNsu: $ultNsu,
            ));

            $ultimoCstat = $response->statusCodigo;
            $ultimaMensagem = DistribuicaoDfeMensagens::formatarMotivo(
                $response->statusCodigo,
                $response->statusMotivo,
            );

            foreach ($response->documentos as $documento) {
                $documentos++;
                $resultado = $this->importarDocumento($documento, $empresa);

                if ($resultado === 'importada') {
                    $importadas++;
                } elseif ($resultado === 'atualizada') {
                    $atualizadas++;
                }
            }

            $ultNsu = $response->ultNsu;
            $parametros->update(['dfe_ultimo_nsu' => $ultNsu]);
            DistribuicaoDfeConfig::limparBloqueioSefaz($parametros);

            if ($response->statusCodigo === DistribuicaoDfeMensagens::CSTAT_SEM_DOCUMENTOS || ! $response->possuiMaisDocumentos()) {
                break;
            }
        }

        if ($importadas > 0 || $atualizadas > 0) {
            ContadorCloudProcessPendingJob::dispatch((int) $empresa->id)->afterResponse();
        }

        return [
            'importadas' => $importadas,
            'atualizadas' => $atualizadas,
            'documentos' => $documentos,
            'ultimo_nsu' => DfeDistribuidor::normalizarNsu($ultNsu),
            'mensagem' => $ultimaMensagem,
            'cstat' => $ultimoCstat,
        ];
    }

    private function importarDocumento(DfeResumoNfe $documento, Empresa $empresa): string
    {
        if (blank($documento->chave)) {
            return 'ignorada';
        }

        // Portal do Contador em lote: só grava pendente; HTTP vai após a resposta (evita timeout 30s).
        $importado = $this->importService->importarResumo($documento, $empresa, syncImmediate: false);

        return $importado['criada'] ? 'importada' : 'atualizada';
    }
}
