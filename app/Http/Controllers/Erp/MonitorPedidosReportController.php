<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\CargaResource;
use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Http\Controllers\Controller;
use App\Models\Carga;
use App\Models\CargaPedido;
use App\Models\Empresa;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\MonitorPedidosReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MonitorPedidosReportController extends Controller
{
    public function __invoke(Request $request): View|Response
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? session('erp_empresa_id') ?? $user->empresa_id ?? 0);
        $orderIds = $this->parseIds($request, 'ids');
        $vendaIds = $this->parseIds($request, 'venda_ids');
        $cargaIds = $this->parseIds($request, 'carga_ids');
        $fromCargas = $request->query('from') === 'cargas' || $cargaIds !== [];

        abort_if($orderIds === [] && $vendaIds === [], 404);

        $empresa = $empresaId > 0
            ? Empresa::query()->find($empresaId)
            : $user->empresa;

        $ordenacao = MonitorPedidosReport::normalizeOrdenacao($request->query('ord'));

        $impValorLiquido = filter_var(
            $empresa?->param_monitor_vendas_imp_valor_liquido ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );

        $cargaNumeroByVendaId = [];
        $cargaCabecalho = null;

        if ($cargaIds !== []) {
            $cargas = Carga::query()
                ->whereIn('id', $cargaIds)
                ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
                ->orderBy('numero')
                ->get(['id', 'numero']);

            $numeros = $cargas
                ->pluck('numero')
                ->map(static fn ($numero): string => (string) $numero)
                ->filter(static fn (string $numero): bool => $numero !== '')
                ->values()
                ->all();

            if ($numeros !== []) {
                $cargaCabecalho = count($numeros) === 1
                    ? 'Carga Nº '.$numeros[0]
                    : 'Cargas Nº '.implode(', ', $numeros);
            }

            $numeroPorCargaId = $cargas->mapWithKeys(
                static fn (Carga $carga): array => [(int) $carga->id => (string) $carga->numero]
            )->all();

            $cargaNumeroByVendaId = CargaPedido::query()
                ->whereIn('carga_id', $cargas->pluck('id')->all())
                ->get(['carga_id', 'pedido_id'])
                ->mapWithKeys(static function (CargaPedido $row) use ($numeroPorCargaId): array {
                    $pedidoId = (int) $row->pedido_id;
                    $cargaId = (int) $row->carga_id;

                    return [$pedidoId => (string) ($numeroPorCargaId[$cargaId] ?? '')];
                })
                ->all();
        }

        $blocos = $vendaIds !== []
            ? MonitorPedidosReport::buildBlocosFromVendas(
                $vendaIds,
                $empresaId > 0 ? $empresaId : null,
                $cargaNumeroByVendaId,
                $impValorLiquido,
                $ordenacao,
            )
            : MonitorPedidosReport::buildBlocos(
                $orderIds,
                $empresaId > 0 ? $empresaId : null,
                $impValorLiquido,
                $ordenacao,
            );

        abort_if($blocos === [], 404);

        $reportQuery = $vendaIds !== []
            ? [
                'venda_ids' => implode(',', $vendaIds),
                'carga_ids' => $cargaIds !== [] ? implode(',', $cargaIds) : null,
                'from' => $fromCargas ? 'cargas' : null,
                'ord' => $ordenacao,
            ]
            : [
                'ids' => implode(',', $orderIds),
                'ord' => $ordenacao,
            ];

        $reportQuery = array_filter($reportQuery, static fn ($value) => $value !== null && $value !== '');

        $impSemColunaDesconto = filter_var(
            $empresa?->param_monitor_vendas_imp_sem_coluna_desconto ?? true,
            FILTER_VALIDATE_BOOLEAN,
        );

        $data = [
            'empresa' => $empresa,
            'blocos' => $blocos,
            'reportTitle' => 'Relatório de Pedidos',
            'cargaCabecalho' => $cargaCabecalho,
            'printedAt' => now(),
            'printedBy' => (string) ($user->name ?: $user->email ?: 'USUARIO'),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'reportUrl' => route('erp.reports.monitor-pedidos'),
            'queryParams' => $reportQuery,
            'ordenacao' => $ordenacao,
            'ordenacaoLabels' => MonitorPedidosReport::ordenacaoLabels(),
            'pdfQuery' => array_merge($reportQuery, ['pdf' => 1]),
            'closeUrl' => $fromCargas
                ? CargaResource::getUrl('index')
                : ForcaVendasMonitorResource::getUrl('index'),
            'autoPrint' => $request->boolean('auto'),
            'idsParam' => $vendaIds !== [] ? implode(',', $vendaIds) : implode(',', $orderIds),
            'impSemColunaDesconto' => $impSemColunaDesconto,
        ];

        if ($request->boolean('pdf')) {
            $pdf = Pdf::loadView('reports.monitor-pedidos-pdf', array_merge($data, [
                'isPdf' => true,
            ]))
                ->setPaper('a4', 'portrait');

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('Helvetica');
            $canvas->page_text(
                $canvas->get_width() - 95,
                $canvas->get_height() - 24,
                'Pag: {PAGE_NUM} de {PAGE_COUNT}',
                $font,
                7,
                [0.3, 0.3, 0.3]
            );

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="relatorio-pedidos.pdf"',
            ]);
        }

        return view('reports.monitor-pedidos', $data);
    }

    /**
     * @return list<int>
     */
    protected function parseIds(Request $request, string $key = 'ids'): array
    {
        $idsRaw = trim((string) $request->query($key, ''));

        if ($idsRaw === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', explode(',', $idsRaw)))));
    }

    protected function formatEmpresaEndereco(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

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
