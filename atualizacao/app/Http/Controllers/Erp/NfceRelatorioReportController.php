<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\NfceResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Queries\NfceListQueryBuilder;
use App\Support\Erp\Reports\NfceRelatorioReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class NfceRelatorioReportController extends Controller
{
    public function __invoke(Request $request): View|Response
    {
        $empresa = $this->currentEmpresa();
        $builder = NfceListQueryBuilder::fromRequest($request);

        if (! $builder->empresaId && $empresa) {
            $builder = new NfceListQueryBuilder(
                statusFilter: $builder->statusFilter,
                searchColumn: $builder->searchColumn,
                localSearch: $builder->localSearch,
                periodoDe: $builder->periodoDe,
                periodoAte: $builder->periodoAte,
                chaveFilter: $builder->chaveFilter,
                empresaId: (int) $empresa->id,
            );
        }

        $nfces = $builder->build()->get();
        [$empresaEnderecoLinha1, $empresaEnderecoLinha2] = $this->formatEmpresaEnderecoLinhas($empresa);

        $data = [
            'empresa' => $empresa,
            'reportTitle' => 'RELATÓRIO DE NFC-e',
            'statusLabel' => NfceRelatorioReport::statusLabel($builder->statusFilter),
            'periodLabel' => NfceRelatorioReport::periodSummary($builder->periodoDe, $builder->periodoAte),
            'resumidoRows' => NfceRelatorioReport::buildResumido($nfces),
            'detalhadoSections' => NfceRelatorioReport::buildDetalhado($nfces),
            'tributacaoRows' => NfceRelatorioReport::buildTributacao($nfces),
            'grandTotal' => NfceRelatorioReport::formatMoney(NfceRelatorioReport::grandTotal($nfces)),
            'printedAt' => now(),
            'empresaEnderecoLinha1' => $empresaEnderecoLinha1,
            'empresaEnderecoLinha2' => $empresaEnderecoLinha2,
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'filters' => $this->filterState($request, $builder),
            'filterOptions' => [
                'status' => PdvVendaNfce::tabLabels(),
            ],
            'reportUrl' => route('erp.reports.nfce-relatorio'),
            'closeUrl' => $this->closeUrl($builder),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.nfce-relatorio-pdf', $data)
                ->setPaper('a4', 'landscape')
                ->download('relatorio-nfce.pdf');
        }

        return view('reports.nfce-relatorio', $data);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterState(Request $request, NfceListQueryBuilder $builder): array
    {
        return [
            'status' => $builder->statusFilter,
            'de' => $builder->periodoDe,
            'ate' => $builder->periodoAte,
            'chave' => $builder->chaveFilter,
            'campo' => $builder->searchColumn,
            'q' => $builder->localSearch,
        ];
    }

    protected function closeUrl(NfceListQueryBuilder $builder): string
    {
        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        $url = NfceResource::getUrl('index');

        return $params === [] ? $url : $url.'?'.http_build_query($params);
    }

    protected function currentEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function formatEmpresaEnderecoLinhas(?Empresa $empresa): array
    {
        if (! $empresa) {
            return ['', ''];
        }

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

    protected function logoDataUri(?Empresa $empresa): ?string
    {
        if (! $empresa || blank($empresa->logo_path)) {
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
