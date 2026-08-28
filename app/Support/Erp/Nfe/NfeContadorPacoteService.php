<?php

namespace App\Support\Erp\Nfe;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Support\ContadorCloud\ContadorCloudConfig;
use App\Support\Erp\Reports\NfceRelatorioReportService;
use App\Support\Erp\Reports\NfeListagemReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class NfeContadorPacoteService
{
    /**
     * @return array{
     *     path: string,
     *     name: string,
     *     competencia: string,
     *     totalNotas: int,
     *     totalXml: int,
     *     periodo: array{de: string, ate: string, label: string, labelShort: string}
     * }
     */
    public function buildPacoteMensal(Empresa $empresa, string $competencia): array
    {
        $periodo = NfceRelatorioReportService::competenciaPeriod($competencia);
        $config = ContadorCloudConfig::fromEmpresa($empresa);
        $nfes = $this->nfesForCompetencia($empresa, $competencia, $config->enviarCanceladas);

        $workDir = storage_path('app/temp/nfe-contador/'.uniqid('pacote-', true));

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Não foi possível criar a pasta temporária do pacote.');
        }

        $xmlDir = $workDir.DIRECTORY_SEPARATOR.'xml';

        if (! mkdir($xmlDir, 0755, true) && ! is_dir($xmlDir)) {
            throw new RuntimeException('Não foi possível criar a pasta de XML do pacote.');
        }

        $xmlCount = $this->exportXmlFiles($nfes, $xmlDir, $config->enviarCanceladas);

        $reportFileName = 'relatorio-nfe-'.$competencia.'.pdf';
        $report = $this->storeRelatorioPdf($empresa, $nfes, $periodo, $reportFileName);
        File::copy($report['path'], $workDir.DIRECTORY_SEPARATOR.$reportFileName);

        $zipName = $this->zipFileName($empresa, $competencia);
        $zipPath = storage_path('app/temp/nfe-contador/'.$zipName);

        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo ZIP.');
        }

        $this->addDirectoryToZip($zip, $workDir, '');
        $zip->close();

        File::deleteDirectory($workDir);
        @unlink($report['path']);

        return [
            'path' => $zipPath,
            'name' => $zipName,
            'competencia' => $competencia,
            'totalNotas' => $nfes->count(),
            'totalXml' => $xmlCount,
            'periodo' => $periodo,
        ];
    }

    public function resolveContadorEmail(Empresa $empresa): string
    {
        $contador = \App\Models\Contador::paraEnvioEmail();

        return trim((string) ($contador?->email ?? ''));
    }

    public function resolveContadorPhone(Empresa $empresa): string
    {
        $contador = \App\Models\Contador::paraEnvioEmail();

        return trim((string) ($contador?->fone ?? ''));
    }

    /**
     * @param  array{labelShort: string}  $periodo
     */
    public function defaultEmailSubject(Empresa $empresa, array $periodo): string
    {
        return 'PACOTE NFE '.$periodo['labelShort'];
    }

    /**
     * @param  array{labelShort: string}  $periodo
     */
    public function defaultEmailMessage(Empresa $empresa, array $periodo, int $totalNotas, int $totalXml): string
    {
        return 'SEGUE EM ANEXO PACOTE NFE REFERENTE A '.$periodo['labelShort'];
    }

    public function expectedZipFileName(Empresa $empresa, string $competencia): string
    {
        return $this->zipFileName($empresa, $competencia);
    }

    /**
     * @return EloquentCollection<int, Nfe>
     */
    public function nfesForCompetencia(Empresa $empresa, string $competencia, bool $includeCanceladas): EloquentCollection
    {
        $periodo = NfceRelatorioReportService::competenciaPeriod($competencia);
        $statuses = [Nfe::STATUS_TRANSMITIDA];

        if ($includeCanceladas) {
            $statuses[] = Nfe::STATUS_CANCELADA;
        }

        return Nfe::query()
            ->with(['cliente'])
            ->where('empresa_id', (int) $empresa->id)
            ->whereIn('status', $statuses)
            ->whereDate('data_emissao', '>=', $periodo['de'])
            ->whereDate('data_emissao', '<=', $periodo['ate'])
            ->orderBy('numero')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Nfe>  $nfes
     * @param  array{de: string, ate: string, label: string, labelShort: string}  $periodo
     * @return array{path: string, name: string}
     */
    protected function storeRelatorioPdf(
        Empresa $empresa,
        EloquentCollection $nfes,
        array $periodo,
        string $fileName,
    ): array {
        $directory = storage_path('app/temp/nfe-relatorio');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.$fileName;

        if (is_file($path)) {
            @unlink($path);
        }

        $columns = NfeListagemReport::defaultColumns();

        $data = [
            'empresa' => $empresa,
            'nfes' => $nfes,
            'columns' => $columns,
            'columnLabels' => NfeListagemReport::columnDefinitions(),
            'reportTitle' => 'LISTAGEM DE NF-e',
            'statusLabel' => 'Transmitidas',
            'orderLabel' => 'Número',
            'locateLabel' => 'Período: '.$periodo['labelShort'],
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa->logoUrl(),
        ];

        Pdf::loadView('reports.nfe-listagem-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->save($path);

        return [
            'path' => $path,
            'name' => $fileName,
        ];
    }

    /**
     * @param  EloquentCollection<int, Nfe>  $nfes
     */
    protected function exportXmlFiles(EloquentCollection $nfes, string $xmlDir, bool $includeCanceladas): int
    {
        $count = 0;

        foreach ($nfes as $nfe) {
            $chave = preg_replace('/\D/', '', (string) ($nfe->chave ?? '')) ?? '';

            if ($chave === '') {
                continue;
            }

            $xml = trim((string) ($nfe->xml ?? ''));

            if ($xml !== '') {
                file_put_contents($xmlDir.DIRECTORY_SEPARATOR.$chave.'.xml', $xml);
                $count++;
            }

            if ($includeCanceladas && $nfe->status === Nfe::STATUS_CANCELADA) {
                $xmlCancelamento = trim((string) ($nfe->xml_cancelamento ?? ''));

                if ($xmlCancelamento !== '') {
                    file_put_contents($xmlDir.DIRECTORY_SEPARATOR.$chave.'-cancel.xml', $xmlCancelamento);
                    $count++;
                }
            }
        }

        return $count;
    }

    protected function zipFileName(Empresa $empresa, string $competencia): string
    {
        $cnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?? '')) ?? '';
        $suffix = $cnpj !== '' ? $cnpj.'_' : '';

        return 'NFE_'.$suffix.$competencia.'.zip';
    }

    protected function addDirectoryToZip(ZipArchive $zip, string $directory, string $relativePrefix): void
    {
        $items = scandir($directory) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $directory.DIRECTORY_SEPARATOR.$item;
            $zipPath = ltrim($relativePrefix.'/'.$item, '/');

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirectoryToZip($zip, $fullPath, $zipPath);

                continue;
            }

            $zip->addFile($fullPath, $zipPath);
        }
    }

    protected function formatEmpresaEndereco(Empresa $empresa): string
    {
        $partes = array_filter([
            filled($empresa->endereco) ? mb_strtoupper(trim($empresa->endereco), 'UTF-8') : null,
            filled($empresa->numero) ? trim((string) $empresa->numero) : null,
            filled($empresa->bairro) ? mb_strtoupper(trim($empresa->bairro), 'UTF-8') : null,
        ]);

        if ($partes === []) {
            return '';
        }

        $endereco = array_shift($partes);

        if ($partes !== []) {
            $endereco .= ', '.implode(' - ', $partes);
        }

        return 'END: '.$endereco;
    }

    protected function logoDataUri(Empresa $empresa): ?string
    {
        if (blank($empresa->logo_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($empresa->logo_path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($empresa->logo_path);
        $mime = Storage::disk('public')->mimeType($empresa->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
