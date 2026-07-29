<?php

namespace App\Support\Erp\Nfce;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Support\ContadorCloud\ContadorCloudConfig;
use App\Support\Erp\Reports\NfceRelatorioReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class NfceContadorPacoteService
{
    public function __construct(
        protected NfceRelatorioReportService $relatorioReportService = new NfceRelatorioReportService,
    ) {}

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
        $nfces = $this->nfcesForCompetencia($empresa, $competencia, $config->enviarCanceladas);

        $workDir = storage_path('app/temp/nfce-contador/'.uniqid('pacote-', true));

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Não foi possível criar a pasta temporária do pacote.');
        }

        $xmlDir = $workDir.DIRECTORY_SEPARATOR.'xml';

        if (! mkdir($xmlDir, 0755, true) && ! is_dir($xmlDir)) {
            throw new RuntimeException('Não foi possível criar a pasta de XML do pacote.');
        }

        $xmlCount = $this->exportXmlFiles($nfces, $xmlDir, $config->enviarCanceladas);

        $reportData = $this->relatorioReportService->buildViewData(
            empresa: $empresa,
            nfces: $nfces,
            statusFilter: PdvVendaNfce::TAB_TRANSMITIDOS,
            periodoDe: $periodo['de'],
            periodoAte: $periodo['ate'],
        );

        $reportFileName = 'relatorio-nfce-'.$competencia.'.pdf';
        $report = $this->relatorioReportService->storePdf($reportData, $reportFileName);
        File::copy($report['path'], $workDir.DIRECTORY_SEPARATOR.$reportFileName);

        $zipName = $this->zipFileName($empresa, $competencia);
        $zipPath = storage_path('app/temp/nfce-contador/'.$zipName);

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
            'totalNotas' => $nfces->count(),
            'totalXml' => $xmlCount,
            'periodo' => $periodo,
        ];
    }

    public function resolveContadorEmail(Empresa $empresa): string
    {
        $config = ContadorCloudConfig::fromEmpresa($empresa);

        if ($config->email !== '') {
            return $config->email;
        }

        if ($config->contadorId) {
            $contador = \App\Models\Contador::query()->find($config->contadorId);

            if (filled($contador?->email)) {
                return trim((string) $contador->email);
            }
        }

        return '';
    }

    public function resolveContadorPhone(Empresa $empresa): string
    {
        $config = ContadorCloudConfig::fromEmpresa($empresa);

        if (! $config->contadorId) {
            return '';
        }

        $contador = \App\Models\Contador::query()->find($config->contadorId);

        return trim((string) ($contador?->fone ?? ''));
    }

    public function defaultEmailSubject(Empresa $empresa, array $periodo): string
    {
        return 'PACOTE NFCE '.$periodo['labelShort'];
    }

    public function defaultEmailMessage(Empresa $empresa, array $periodo, int $totalNotas, int $totalXml): string
    {
        return 'SEGUE EM ANEXO PACOTE NFCE REFERENTE A '.$periodo['labelShort'];
    }

    public function defaultWhatsAppMessage(array $periodo): string
    {
        return 'SEGUE EM ANEXO PACOTE NFCE REFERENTE A '.$periodo['labelShort'];
    }

    /**
     * @return EloquentCollection<int, PdvVendaNfce>
     */
    public function nfcesForCompetencia(Empresa $empresa, string $competencia, bool $includeCanceladas): EloquentCollection
    {
        $periodo = NfceRelatorioReportService::competenciaPeriod($competencia);
        $statuses = [PdvVendaNfce::STATUS_AUTORIZADA];

        if ($includeCanceladas) {
            $statuses[] = PdvVendaNfce::STATUS_CANCELADA;
        }

        return PdvVendaNfce::query()
            ->with(['pdvVenda.itens.product'])
            ->whereIn('status', $statuses)
            ->where(function (Builder $outer) use ($empresa): void {
                $empresaId = (int) $empresa->id;
                $outer->where('empresa_id', $empresaId)
                    ->orWhere(function (Builder $inner) use ($empresaId): void {
                        $inner->whereNull('empresa_id')
                            ->whereHas('pdvVenda.sessao', fn (Builder $sessao): Builder => $sessao
                                ->where('empresa_id', $empresaId));
                    });
            })
            ->where(function (Builder $query) use ($periodo): void {
                $query->whereBetween('autorizada_em', [
                    Carbon::parse($periodo['de'])->startOfDay(),
                    Carbon::parse($periodo['ate'])->endOfDay(),
                ])->orWhere(function (Builder $fallback) use ($periodo): void {
                    $fallback->whereNull('autorizada_em')
                        ->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda
                            ->whereDate('fechado_em', '>=', $periodo['de'])
                            ->whereDate('fechado_em', '<=', $periodo['ate']));
                });
            })
            ->orderBy('id')
            ->get();
    }

    protected function exportXmlFiles(EloquentCollection $nfces, string $xmlDir, bool $includeCanceladas): int
    {
        $count = 0;

        foreach ($nfces as $nfce) {
            $chave = preg_replace('/\D/', '', (string) ($nfce->chave ?? '')) ?? '';

            if ($chave === '') {
                continue;
            }

            $xml = trim((string) ($nfce->xml ?? ''));

            if ($xml !== '') {
                file_put_contents($xmlDir.DIRECTORY_SEPARATOR.$chave.'.xml', $xml);
                $count++;
            }

            if ($includeCanceladas && $nfce->status === PdvVendaNfce::STATUS_CANCELADA) {
                $xmlCancelamento = trim((string) ($nfce->xml_cancelamento ?? ''));

                if ($xmlCancelamento !== '') {
                    file_put_contents($xmlDir.DIRECTORY_SEPARATOR.$chave.'-cancel.xml', $xmlCancelamento);
                    $count++;
                }
            }
        }

        return $count;
    }

    public function expectedZipFileName(Empresa $empresa, string $competencia): string
    {
        return $this->zipFileName($empresa, $competencia);
    }

    protected function zipFileName(Empresa $empresa, string $competencia): string
    {
        $cnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?? '')) ?? '';
        $suffix = $cnpj !== '' ? $cnpj.'_' : '';

        return 'NFCE_'.$suffix.$competencia.'.zip';
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
}
