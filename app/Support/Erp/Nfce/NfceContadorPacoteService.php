<?php

namespace App\Support\Erp\Nfce;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\ContadorCloud\ContadorCloudConfig;
use App\Support\Erp\Queries\NfceListQueryBuilder;
use App\Support\Erp\Reports\NfceRelatorioReportService;
use App\Support\Fiscal\NfceNumeracao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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
     *     totalInutilizacoes: int,
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

        $inutilizacoes = $this->inutilizacoesForCompetencia($empresa, $competencia);
        $this->exportInutilizacaoXmlFiles($inutilizacoes, $xmlDir);

        $reportData = $this->relatorioReportService->buildViewData(
            empresa: $empresa,
            nfces: $this->relatorioNfcesForCompetencia($empresa, $competencia),
            statusFilter: PdvVendaNfce::TAB_TRANSMITIDOS,
            periodoDe: $periodo['de'],
            periodoAte: $periodo['ate'],
        );
        $reportData['inutilizacoesRows'] = $this->inutilizacoesReportRows($inutilizacoes);

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
            'totalInutilizacoes' => $inutilizacoes->count(),
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

    /**
     * Mesmas notas do F7 (aba Transmitidos, período = 1º ao último dia da competência, empresa da sessão),
     * sem cupons simulados — não têm valor fiscal nem XML para o contador.
     *
     * @return EloquentCollection<int, PdvVendaNfce>
     */
    public function relatorioNfcesForCompetencia(Empresa $empresa, string $competencia): EloquentCollection
    {
        $periodo = NfceRelatorioReportService::competenciaPeriod($competencia);
        $tabela = (new PdvVendaNfce)->getTable();

        return (new NfceListQueryBuilder(
            statusFilter: PdvVendaNfce::TAB_TRANSMITIDOS,
            periodoDe: $periodo['de'],
            periodoAte: $periodo['ate'],
            empresaId: (int) $empresa->id,
        ))
            ->build()
            ->where($tabela.'.status', '<>', PdvVendaNfce::STATUS_SIMULADA)
            ->where(fn (Builder $query): Builder => $query
                ->where($tabela.'.simulada', false)
                ->orWhereNull($tabela.'.simulada'))
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

    /**
     * Inutilizações homologadas na SEFAZ (cStat 102 / protocolo) da empresa, no ambiente fiscal atual,
     * gravadas dentro da competência. Uma por série+faixa (a mais recente), sem repetir protocolo.
     *
     * @return Collection<int, object>
     */
    public function inutilizacoesForCompetencia(Empresa $empresa, string $competencia): Collection
    {
        if (! Schema::hasTable('nfce_inutilizacoes')) {
            return collect();
        }

        $periodo = NfceRelatorioReportService::competenciaPeriod($competencia);
        $ambiente = NfceNumeracao::ambiente(VendasParametro::forEmpresa((int) $empresa->id));

        return DB::table('nfce_inutilizacoes')
            ->where('empresa_id', (int) $empresa->id)
            ->where('modelo', NfceNumeracao::MODELO)
            ->where('ambiente', $ambiente)
            ->whereNotNull('protocolo')
            ->where('protocolo', '<>', '')
            ->where(fn ($q) => $q->where('status_codigo', '102')
                ->orWhereNull('status_codigo')
                ->orWhere('status_codigo', ''))
            ->whereBetween('created_at', [
                Carbon::parse($periodo['de'])->startOfDay(),
                Carbon::parse($periodo['ate'])->endOfDay(),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'serie', 'numero_inicial', 'numero_final', 'protocolo', 'status_codigo', 'justificativa', 'xml', 'created_at'])
            ->unique(fn ($row): string => (int) $row->serie.'|'.(int) $row->numero_inicial.'|'.(int) $row->numero_final)
            ->unique(fn ($row): string => trim((string) $row->protocolo))
            ->sortBy(fn ($row): string => sprintf('%05d-%09d', (int) $row->serie, (int) $row->numero_inicial))
            ->values();
    }

    /**
     * @param  Collection<int, object>  $inutilizacoes
     */
    protected function exportInutilizacaoXmlFiles(Collection $inutilizacoes, string $xmlDir): int
    {
        $count = 0;

        foreach ($inutilizacoes as $row) {
            $xml = trim((string) ($row->xml ?? ''));

            if ($xml === '') {
                continue;
            }

            $protocolo = preg_replace('/\D/', '', (string) $row->protocolo) ?: 'sem-protocolo';
            $nome = sprintf('INUT-S%03d-%09d-%09d-%s.xml', (int) $row->serie, (int) $row->numero_inicial, (int) $row->numero_final, $protocolo);

            file_put_contents($xmlDir.DIRECTORY_SEPARATOR.$nome, $xml);
            $count++;
        }

        return $count;
    }

    /**
     * @param  Collection<int, object>  $inutilizacoes
     * @return list<array{serie: string, faixa: string, data: string, protocolo: string, justificativa: string}>
     */
    protected function inutilizacoesReportRows(Collection $inutilizacoes): array
    {
        return $inutilizacoes->map(function ($row): array {
            $ini = (int) $row->numero_inicial;
            $fim = (int) $row->numero_final;

            return [
                'serie' => (string) (int) $row->serie,
                'faixa' => $ini === $fim ? (string) $ini : $ini.' a '.$fim,
                'data' => $row->created_at ? Carbon::parse($row->created_at)->format('d/m/Y H:i') : '',
                'protocolo' => trim((string) $row->protocolo),
                'justificativa' => mb_strtoupper(trim((string) $row->justificativa), 'UTF-8'),
            ];
        })->all();
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
