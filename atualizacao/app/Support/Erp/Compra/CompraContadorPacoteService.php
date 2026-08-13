<?php

namespace App\Support\Erp\Compra;

use App\Models\Compra;
use App\Models\Empresa;
use App\Models\NotaFornecedor;
use App\Support\ContadorCloud\ContadorCloudConfig;
use App\Support\Erp\Reports\CompraListagemReport;
use App\Support\Erp\Reports\NfceRelatorioReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class CompraContadorPacoteService
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
        $compras = $this->comprasForCompetencia($empresa, $competencia, $config->enviarCanceladas);

        $workDir = storage_path('app/temp/compra-contador/'.uniqid('pacote-', true));

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Não foi possível criar a pasta temporária do pacote.');
        }

        $xmlDir = $workDir.DIRECTORY_SEPARATOR.'xml';

        if (! mkdir($xmlDir, 0755, true) && ! is_dir($xmlDir)) {
            throw new RuntimeException('Não foi possível criar a pasta de XML do pacote.');
        }

        $xmlCount = $this->exportXmlFiles($compras, $xmlDir);

        $reportFileName = 'relatorio-compras-'.$competencia.'.pdf';
        $report = $this->storeRelatorioPdf($empresa, $compras, $periodo, $reportFileName);
        File::copy($report['path'], $workDir.DIRECTORY_SEPARATOR.$reportFileName);

        $zipName = $this->zipFileName($empresa, $competencia);
        $zipPath = storage_path('app/temp/compra-contador/'.$zipName);

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
            'totalNotas' => $compras->count(),
            'totalXml' => $xmlCount,
            'periodo' => $periodo,
        ];
    }

    /**
     * @param  array{labelShort: string}  $periodo
     */
    public function defaultEmailSubject(Empresa $empresa, array $periodo): string
    {
        return 'PACOTE COMPRAS '.$periodo['labelShort'];
    }

    /**
     * @param  array{labelShort: string}  $periodo
     */
    public function defaultEmailMessage(Empresa $empresa, array $periodo, int $totalNotas, int $totalXml): string
    {
        return 'SEGUE EM ANEXO PACOTE DE COMPRAS REFERENTE A '.$periodo['labelShort'];
    }

    public function expectedZipFileName(Empresa $empresa, string $competencia): string
    {
        return $this->zipFileName($empresa, $competencia);
    }

    /**
     * @return EloquentCollection<int, Compra>
     */
    public function comprasForCompetencia(Empresa $empresa, string $competencia, bool $includeCanceladas): EloquentCollection
    {
        $periodo = NfceRelatorioReportService::competenciaPeriod($competencia);
        $statuses = [Compra::STATUS_FECHADA];

        if ($includeCanceladas) {
            $statuses[] = Compra::STATUS_CANCELADA;
        }

        return Compra::query()
            ->with(['fornecedor'])
            ->where('empresa_id', (int) $empresa->id)
            ->whereIn('status', $statuses)
            ->where(function ($query) use ($periodo): void {
                $query->where(function ($inner) use ($periodo): void {
                    $inner->whereNotNull('data_emissao')
                        ->whereDate('data_emissao', '>=', $periodo['de'])
                        ->whereDate('data_emissao', '<=', $periodo['ate']);
                })->orWhere(function ($inner) use ($periodo): void {
                    $inner->whereNull('data_emissao')
                        ->whereNotNull('data_entrada')
                        ->whereDate('data_entrada', '>=', $periodo['de'])
                        ->whereDate('data_entrada', '<=', $periodo['ate']);
                });
            })
            ->orderBy('numero')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Compra>  $compras
     * @param  array{de: string, ate: string, label: string, labelShort: string}  $periodo
     * @return array{path: string, name: string}
     */
    protected function storeRelatorioPdf(
        Empresa $empresa,
        EloquentCollection $compras,
        array $periodo,
        string $fileName,
    ): array {
        $directory = storage_path('app/temp/compra-relatorio');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.$fileName;

        if (is_file($path)) {
            @unlink($path);
        }

        $columns = CompraListagemReport::defaultColumns();

        $data = [
            'empresa' => $empresa,
            'compras' => $compras,
            'columns' => $columns,
            'columnLabels' => CompraListagemReport::columnDefinitions(),
            'reportTitle' => 'LISTAGEM DE COMPRAS',
            'statusLabel' => 'Fechadas',
            'orderLabel' => 'Número',
            'locateLabel' => 'Período: '.$periodo['labelShort'],
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa->logoUrl(),
        ];

        Pdf::loadView('reports.compra-listagem-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->save($path);

        return [
            'path' => $path,
            'name' => $fileName,
        ];
    }

    /**
     * @param  EloquentCollection<int, Compra>  $compras
     */
    protected function exportXmlFiles(EloquentCollection $compras, string $xmlDir): int
    {
        $count = 0;
        $compraIds = $compras->pluck('id')->filter()->all();
        $notasByCompraId = NotaFornecedor::query()
            ->whereIn('compra_id', $compraIds)
            ->whereNotNull('xml')
            ->where('xml', '!=', '')
            ->get()
            ->keyBy('compra_id');

        $chaves = $compras
            ->pluck('chave_nfe')
            ->filter(fn (?string $chave): bool => filled($chave))
            ->map(fn (string $chave): string => preg_replace('/\D/', '', $chave) ?? '')
            ->filter(fn (string $chave): bool => strlen($chave) === 44)
            ->unique()
            ->values()
            ->all();

        $notasByChave = $chaves !== []
            ? NotaFornecedor::query()
                ->whereIn('chave', $chaves)
                ->whereNotNull('xml')
                ->where('xml', '!=', '')
                ->get()
                ->keyBy('chave')
            : collect();

        foreach ($compras as $compra) {
            $xml = $this->resolveXmlForCompra($compra, $notasByCompraId, $notasByChave);

            if ($xml === '') {
                continue;
            }

            $chave = preg_replace('/\D/', '', (string) ($compra->chave_nfe ?? '')) ?? '';

            if ($chave === '') {
                $chave = 'compra-'.$compra->id;
            }

            file_put_contents($xmlDir.DIRECTORY_SEPARATOR.$chave.'.xml', $xml);
            $count++;
        }

        return $count;
    }

    /**
     * @param  EloquentCollection<int|string, NotaFornecedor>  $notasByCompraId
     * @param  EloquentCollection<int|string, NotaFornecedor>  $notasByChave
     */
    protected function resolveXmlForCompra(
        Compra $compra,
        EloquentCollection $notasByCompraId,
        EloquentCollection $notasByChave,
    ): string {
        $nota = $notasByCompraId->get($compra->id);

        if (! $nota) {
            $chave = preg_replace('/\D/', '', (string) ($compra->chave_nfe ?? '')) ?? '';

            if ($chave !== '') {
                $nota = $notasByChave->get($chave);
            }
        }

        return trim((string) ($nota?->xml ?? ''));
    }

    protected function zipFileName(Empresa $empresa, string $competencia): string
    {
        $cnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?? '')) ?? '';
        $suffix = $cnpj !== '' ? $cnpj.'_' : '';

        return 'COMPRAS_'.$suffix.$competencia.'.zip';
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
