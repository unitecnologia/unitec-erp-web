<?php

namespace App\Support\Erp\Reports;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class NfceRelatorioReportService
{
    /**
     * @return array<string, mixed>
     */
    public function buildViewData(
        Empresa $empresa,
        EloquentCollection $nfces,
        string $statusFilter = PdvVendaNfce::TAB_TRANSMITIDOS,
        ?string $periodoDe = null,
        ?string $periodoAte = null,
    ): array {
        [$empresaEnderecoLinha1, $empresaEnderecoLinha2] = $this->formatEmpresaEnderecoLinhas($empresa);

        return [
            'empresa' => $empresa,
            'reportTitle' => 'RELATÓRIO DE NFC-e',
            'statusLabel' => NfceRelatorioReport::statusLabel($statusFilter),
            'periodLabel' => NfceRelatorioReport::periodSummary($periodoDe, $periodoAte),
            'resumidoRows' => NfceRelatorioReport::buildResumido($nfces),
            'detalhadoSections' => NfceRelatorioReport::buildDetalhado($nfces),
            'tributacaoRows' => NfceRelatorioReport::buildTributacao($nfces),
            'grandTotal' => NfceRelatorioReport::formatMoney(NfceRelatorioReport::grandTotal($nfces)),
            'printedAt' => now(),
            'empresaEnderecoLinha1' => $empresaEnderecoLinha1,
            'empresaEnderecoLinha2' => $empresaEnderecoLinha2,
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa->logoUrl(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{path: string, name: string}
     */
    public function storePdf(array $data, string $fileName): array
    {
        $directory = storage_path('app/temp/nfce-relatorio');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.$fileName;

        if (is_file($path)) {
            @unlink($path);
        }

        Pdf::loadView('reports.nfce-relatorio-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->save($path);

        return [
            'path' => $path,
            'name' => $fileName,
        ];
    }

    /**
     * @return array{de: string, ate: string, label: string, labelShort: string}
     */
    public static function competenciaPeriod(string $competencia): array
    {
        $date = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();

        return [
            'de' => $date->copy()->startOfMonth()->format('Y-m-d'),
            'ate' => $date->copy()->endOfMonth()->format('Y-m-d'),
            'label' => mb_strtoupper($date->translatedFormat('F/Y'), 'UTF-8'),
            'labelShort' => $date->format('m/Y'),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function formatEmpresaEnderecoLinhas(Empresa $empresa): array
    {
        $rua = filled($empresa->endereco) ? mb_strtoupper(trim($empresa->endereco), 'UTF-8') : null;
        $numero = filled($empresa->numero) ? trim((string) $empresa->numero) : null;
        $bairro = filled($empresa->bairro) ? mb_strtoupper(trim($empresa->bairro), 'UTF-8') : null;
        $cidade = filled($empresa->cidade) ? mb_strtoupper(trim($empresa->cidade), 'UTF-8') : null;
        $uf = filled($empresa->uf) ? strtoupper(trim((string) $empresa->uf)) : null;

        $linha1Partes = [];

        if ($rua) {
            $linha1Partes[] = $numero ? $rua.', '.$numero : $rua;
        }

        if ($bairro) {
            $linha1Partes[] = $bairro;
        }

        $linha2 = $cidade;

        if ($uf) {
            $linha2 = $linha2 ? $linha2.'- '.$uf : $uf;
        }

        return [
            implode(' - ', array_filter($linha1Partes)),
            (string) $linha2,
        ];
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
