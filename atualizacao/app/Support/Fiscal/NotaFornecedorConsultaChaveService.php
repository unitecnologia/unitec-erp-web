<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\NotaFornecedor;
use App\Models\VendasParametro;
use Unitec\FiscalEngine\Dto\ConsultarDistribuicaoDfeRequest;
use Unitec\FiscalEngine\Dto\DfeResumoNfe;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;

final class NotaFornecedorConsultaChaveService
{
    public function __construct(
        private readonly FiscalEngine $engine = new FiscalEngine(),
        private readonly NotaFornecedorImportService $importService = new NotaFornecedorImportService(),
    ) {}

    /**
     * @return array{nota: NotaFornecedor, criada: bool, mensagem: string}
     */
    public function consultar(Empresa $empresa, string $chave): array
    {
        $parametros = VendasParametro::forEmpresa((int) $empresa->id);
        DistribuicaoDfeConfig::validarCertificado($parametros, $empresa);

        $chave = preg_replace('/\D/', '', $chave) ?? '';

        if (strlen($chave) !== 44) {
            throw new FiscalEngineException('Informe a chave de acesso da NF-e com 44 dígitos.');
        }

        if (substr($chave, 20, 2) !== '55') {
            throw new FiscalEngineException('Somente chaves de NF-e (modelo 55) são aceitas nesta consulta.');
        }

        $certificate = NfceFiscalCertificateResolver::resolve($empresa, $parametros);
        $tpAmb = NfceFiscalCertificateResolver::tpAmb($parametros);

        $response = $this->engine->consultarDistribuicaoDfe(new ConsultarDistribuicaoDfeRequest(
            certificate: $certificate,
            cnpj: (string) $empresa->cnpj,
            cUfAutor: DistribuicaoDfeConfig::cUfAutor($empresa, $parametros),
            tpAmb: $tpAmb,
            chave: $chave,
        ));

        $resumo = $this->resolverDocumento($response->documentos, $chave);

        if ($resumo === null) {
            throw new FiscalEngineException(
                'NF-e não localizada na Distribuição DF-e para o CNPJ da empresa. '
                    . 'Verifique se a nota foi emitida contra este destinatário.',
            );
        }

        $importado = $this->importService->importarResumo($resumo, $empresa);

        return [
            'nota' => $importado['nota'],
            'criada' => $importado['criada'],
            'mensagem' => DistribuicaoDfeMensagens::formatarMotivo(
                $response->statusCodigo,
                $response->statusMotivo,
            ),
        ];
    }

    /**
     * @param  list<DfeResumoNfe>  $documentos
     */
    private function resolverDocumento(array $documentos, string $chave): ?DfeResumoNfe
    {
        foreach ($documentos as $documento) {
            if ($documento->chave === $chave) {
                return $documento;
            }
        }

        return $documentos[0] ?? null;
    }
}
