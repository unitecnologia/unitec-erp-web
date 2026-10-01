<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Support\ContadorCloud\ContadorCloudConfig;
use App\Support\Erp\Reports\NfceRelatorioReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class NfseContadorPacoteService
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
        $nfses = $this->nfsesForCompetencia($empresa, $competencia, $config->enviarCanceladas);

        $workDir = storage_path('app/temp/nfse-contador/'.uniqid('pacote-', true));

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Não foi possível criar a pasta temporária do pacote.');
        }

        $xmlDir = $workDir.DIRECTORY_SEPARATOR.'xml';

        if (! mkdir($xmlDir, 0755, true) && ! is_dir($xmlDir)) {
            throw new RuntimeException('Não foi possível criar a pasta de XML do pacote.');
        }

        $xmlCount = $this->exportXmlFiles($nfses, $xmlDir);

        $reportFileName = 'relatorio-nfse-'.$competencia.'.pdf';
        $report = $this->storeRelatorioPdf($empresa, $nfses, $periodo, $reportFileName);
        File::copy($report['path'], $workDir.DIRECTORY_SEPARATOR.$reportFileName);

        $zipName = $this->zipFileName($empresa, $competencia);
        $zipPath = storage_path('app/temp/nfse-contador/'.$zipName);

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
            'totalNotas' => $nfses->count(),
            'totalXml' => $xmlCount,
            'periodo' => $periodo,
        ];
    }

    /**
     * @return EloquentCollection<int, Nfse>
     */
    public function nfsesForCompetencia(Empresa $empresa, string $competencia, bool $includeCanceladas): EloquentCollection
    {
        $periodo = NfceRelatorioReportService::competenciaPeriod($competencia);
        $statuses = [Nfse::STATUS_AUTORIZADA];

        if ($includeCanceladas) {
            $statuses[] = Nfse::STATUS_CANCELADA;
        }

        return Nfse::query()
            ->where('empresa_id', (int) $empresa->id)
            ->whereIn('status', $statuses)
            ->where(function ($query) use ($periodo): void {
                $query->whereBetween('data_emissao', [$periodo['de'], $periodo['ate']])
                    ->orWhere(function ($fallback) use ($periodo): void {
                        $fallback->whereNull('data_emissao')
                            ->whereBetween('competencia', [$periodo['de'], $periodo['ate']]);
                    });
            })
            ->orderBy('data_emissao')
            ->orderBy('id')
            ->get();
    }

    public function defaultEmailSubject(Empresa $empresa, array $periodo): string
    {
        return 'PACOTE NFSE '.$periodo['labelShort'];
    }

    public function defaultEmailMessage(Empresa $empresa, array $periodo, int $totalNotas, int $totalXml): string
    {
        return 'SEGUE EM ANEXO PACOTE NFSE REFERENTE A '.$periodo['labelShort'];
    }

    public function expectedZipFileName(Empresa $empresa, string $competencia): string
    {
        return $this->zipFileName($empresa, $competencia);
    }

    /**
     * @param  EloquentCollection<int, Nfse>  $nfses
     * @param  array{de: string, ate: string, label: string, labelShort: string}  $periodo
     * @return array{path: string, name: string}
     */
    protected function storeRelatorioPdf(Empresa $empresa, EloquentCollection $nfses, array $periodo, string $fileName): array
    {
        $directory = storage_path('app/temp/nfse-contador');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.$fileName;

        if (is_file($path)) {
            @unlink($path);
        }

        $rows = $nfses->map(static function (Nfse $nfse): array {
            return [
                'numero' => (string) ($nfse->numero_nfse ?: $nfse->numero_dps ?: $nfse->id),
                'emissao' => optional($nfse->data_emissao)?->format('d/m/Y') ?: '—',
                'tomador' => mb_strtoupper((string) ($nfse->tomador_nome ?: '—'), 'UTF-8'),
                'chave' => (string) ($nfse->chave_acesso ?: $nfse->chave ?: '—'),
                'status' => mb_strtoupper($nfse->statusLabel(), 'UTF-8'),
                'total' => number_format((float) $nfse->total, 2, ',', '.'),
            ];
        })->all();

        $grandTotal = number_format((float) $nfses->sum(fn (Nfse $n): float => (float) $n->total), 2, ',', '.');

        Pdf::loadView('reports.nfse-contador-pacote-pdf', [
            'empresa' => $empresa,
            'periodo' => $periodo,
            'rows' => $rows,
            'grandTotal' => $grandTotal,
            'printedAt' => now(),
            'logoDataUri' => $this->logoDataUri($empresa),
        ])->setPaper('a4', 'landscape')->save($path);

        return ['path' => $path, 'name' => $fileName];
    }

    /**
     * @param  EloquentCollection<int, Nfse>  $nfses
     */
    protected function exportXmlFiles(EloquentCollection $nfses, string $xmlDir): int
    {
        $count = 0;

        foreach ($nfses as $nfse) {
            $xml = trim((string) ($nfse->xml_nfse ?? ''));

            if ($xml === '') {
                continue;
            }

            $base = preg_replace('/\D+/', '', (string) ($nfse->chave_acesso ?: $nfse->chave ?: $nfse->numero_nfse ?: $nfse->id)) ?: (string) $nfse->id;
            file_put_contents($xmlDir.DIRECTORY_SEPARATOR.'NFSe-'.$base.'.xml', $xml);
            $count++;
        }

        return $count;
    }

    protected function zipFileName(Empresa $empresa, string $competencia): string
    {
        $cnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?? '')) ?? '';
        $suffix = $cnpj !== '' ? $cnpj.'_' : '';

        return 'NFSE_'.$suffix.$competencia.'.zip';
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

    protected function logoDataUri(Empresa $empresa): ?string
    {
        if (blank($empresa->logo_path) || ! Storage::disk('public')->exists($empresa->logo_path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($empresa->logo_path);
        $mime = Storage::disk('public')->mimeType($empresa->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
