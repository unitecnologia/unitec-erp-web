<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\CargaResource;
use App\Http\Controllers\Controller;
use App\Models\Carga;
use App\Models\Empresa;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\CargaRomaneioReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CargaRomaneioReportController extends Controller
{
    public function __invoke(Request $request, Carga $carga): View|Response
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $exibirCliente = $request->boolean('cliente');
        $exibirValor = $request->boolean('valor');
        $ordenacao = CargaRomaneioReport::normalizeOrdenacao($request->query('ord'));

        $cargas = $this->resolveCargas($request, $carga, $empresaId);

        abort_if($cargas->isEmpty(), 404);

        $empresa = $this->currentEmpresa();
        $documentos = $cargas->map(
            fn (Carga $item): array => $this->buildDocumento($item, $ordenacao)
        )->all();

        $idsParam = $cargas->pluck('id')->implode(',');
        $first = $cargas->first();

        $queryParams = [
            'ids' => $idsParam,
            'cliente' => $exibirCliente ? 1 : 0,
            'valor' => $exibirValor ? 1 : 0,
            'ord' => $ordenacao,
        ];

        $data = [
            'empresa' => $empresa,
            'documentos' => $documentos,
            'carga' => $first,
            'exibirCliente' => $exibirCliente,
            'exibirValor' => $exibirValor,
            'ordenacao' => $ordenacao,
            'ordenacaoLabels' => CargaRomaneioReport::ordenacaoLabels(),
            'reportTitle' => 'ROMANEIO DE CARGA',
            'printedAt' => now(),
            'printedBy' => (string) ($user->name ?: $user->email ?: 'USUARIO'),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'reportUrl' => route('erp.reports.carga-romaneio', ['carga' => $first->id]),
            'queryParams' => $queryParams,
            'closeUrl' => CargaResource::getUrl('index'),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            $nome = $cargas->count() === 1
                ? 'romaneio-carga-'.$first->numero.'.pdf'
                : 'romaneio-cargas.pdf';

            $pdf = Pdf::loadView('reports.carga-romaneio-pdf', array_merge($data, [
                'isPdf' => true,
            ]))
                ->setPaper('a4', 'portrait');

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('Helvetica', 'bold');
            $canvas->page_text(
                $canvas->get_width() - 120,
                $canvas->get_height() - 28,
                'Pag: {PAGE_NUM} de {PAGE_COUNT}',
                $font,
                8,
                [0.07, 0.07, 0.07]
            );

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$nome.'"',
            ]);
        }

        return view('reports.carga-romaneio', $data);
    }

    /**
     * @return Collection<int, Carga>
     */
    protected function resolveCargas(Request $request, Carga $carga, int $empresaId): Collection
    {
        $idsRaw = trim((string) $request->query('ids', ''));
        $ids = $idsRaw !== ''
            ? array_values(array_unique(array_filter(array_map('intval', explode(',', $idsRaw)))))
            : [(int) $carga->id];

        if ($ids === []) {
            $ids = [(int) $carga->id];
        }

        $query = Carga::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', Carga::STATUS_CANCELADA)
            ->with([
                'motorista:id,proprietario,apelido',
                'veiculo:id,placa,descricao',
                'pedidos.cliente:id,nome_razao',
                'pedidos.itens',
            ])
            ->orderBy('numero');

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->get();
    }

    /**
     * @return array{
     *     carga: Carga,
     *     pedidos: list<array<string, mixed>>,
     *     produtos: list<array<string, mixed>>,
     *     qtdPedidos: int,
     *     valorTotal: float,
     *     pesoTotalKg: float,
     *     motorista: string,
     *     veiculo: string
     * }
     */
    protected function buildDocumento(Carga $carga, string $ordenacao): array
    {
        $pedidos = CargaRomaneioReport::buildPedidos($carga, $ordenacao);
        $produtos = CargaRomaneioReport::buildResumoProdutos($carga, $ordenacao);

        $motorista = trim((string) ($carga->motorista?->apelido ?: $carga->motorista?->proprietario ?: '—'));
        $veiculo = '—';

        if ($carga->veiculo) {
            $veiculo = (string) $carga->veiculo->placa;
            $descricao = trim((string) ($carga->veiculo->descricao ?? ''));

            if ($descricao !== '') {
                $veiculo .= ' — '.$descricao;
            }
        }

        return [
            'carga' => $carga,
            'pedidos' => $pedidos,
            'produtos' => $produtos,
            'qtdPedidos' => count($pedidos),
            'valorTotal' => (float) collect($pedidos)->sum('valor'),
            'pesoTotalKg' => CargaRomaneioReport::calcularPesoTotalKg($carga),
            'motorista' => $motorista,
            'veiculo' => $veiculo,
        ];
    }

    protected function currentEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
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
