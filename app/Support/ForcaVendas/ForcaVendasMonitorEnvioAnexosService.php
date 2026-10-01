<?php

namespace App\Support\ForcaVendas;

use App\Models\Boleto;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\Nfe;
use App\Models\PdvVendaNfce;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfce\NfceCupomReportService;
use App\Support\Erp\Nfe\NfeDanfeReportService;
use App\Support\Erp\Reports\MonitorPedidosReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Anexos do envio F9 no Monitor: DAV + NF-e + NFC-e + boletos (quando existirem).
 */
final class ForcaVendasMonitorEnvioAnexosService
{
    public function __construct(
        private readonly NfeDanfeReportService $nfeDanfe = new NfeDanfeReportService,
        private readonly NfceCupomReportService $nfceCupom = new NfceCupomReportService,
    ) {}

    public function formatNumeroDav(ForcaVendasOrder $order): string
    {
        $raw = trim((string) ($order->pedido?->numero ?? ''));

        if ($raw === '') {
            return (string) $order->id;
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';

        return $digits !== '' ? (string) (int) $digits : $raw;
    }

    public function defaultEmailSubject(string $numero): string
    {
        return 'PEDIDO / DAV N.'.$numero;
    }

    public function defaultEmailMessage(string $numero): string
    {
        return 'SEGUE EM ANEXO PEDIDO / DAV N.'.$numero.' E DOCUMENTOS RELACIONADOS.';
    }

    /**
     * @return list<array{id: string, name: string, path: string, display: string, owned?: bool}>
     */
    public function montar(ForcaVendasOrder $order): array
    {
        $anexos = [];
        $numero = $this->formatNumeroDav($order);
        $order->loadMissing(['pedido', 'venda.nfes', 'venda.pdvVenda.nfce', 'cliente']);

        if ($order->pedido || $order->venda_id) {
            try {
                $pdf = $this->storeDavPdfAttachment($order);
                if ($pdf !== null) {
                    $anexos[] = [
                        'id' => 'dav-pdf',
                        'name' => 'DAV-'.$numero.'.pdf',
                        'path' => $pdf['path'],
                        'display' => 'DAV '.$numero,
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        foreach ($this->nfesTransmitidas($order) as $nfe) {
            try {
                $pdf = $this->nfeDanfe->storePdfAttachment($nfe);
                $anexos[] = [
                    'id' => 'nfe-pdf-'.$nfe->id,
                    'name' => $pdf['name'],
                    'path' => $pdf['path'],
                    'display' => 'DANFE NF-e '.$nfe->numero,
                ];
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                $xml = $this->nfeDanfe->storeXmlAttachment($nfe);
                if (is_array($xml)) {
                    $anexos[] = [
                        'id' => 'nfe-xml-'.$nfe->id,
                        'name' => $xml['name'],
                        'path' => $xml['path'],
                        'display' => $xml['display'] ?? $xml['name'],
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $pdv = $order->venda?->pdvVenda;
        $nfce = $pdv?->nfce;

        if ($pdv && $nfce && in_array($nfce->status, [
            PdvVendaNfce::STATUS_AUTORIZADA,
            PdvVendaNfce::STATUS_SIMULADA,
            PdvVendaNfce::STATUS_CONTINGENCIA,
        ], true)) {
            try {
                $pdf = $this->nfceCupom->storePdfAttachment($pdv);
                $anexos[] = [
                    'id' => 'nfce-pdf-'.$nfce->id,
                    'name' => $pdf['name'],
                    'path' => $pdf['path'],
                    'display' => $pdf['display'] ?? ('NFC-e '.$nfce->numero),
                ];
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                $xml = $this->nfceCupom->storeXmlAttachment($nfce);
                if (is_array($xml)) {
                    $anexos[] = [
                        'id' => 'nfce-xml-'.$nfce->id,
                        'name' => $xml['name'],
                        'path' => $xml['path'],
                        'display' => $xml['display'] ?? $xml['name'],
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        foreach ($this->boletosDoPedido($order) as $boleto) {
            try {
                $pdf = $this->storeBoletoPdf($boleto, $numero);
                if ($pdf === null) {
                    continue;
                }
                $anexos[] = [
                    'id' => 'boleto-pdf-'.$boleto->id,
                    'name' => $pdf['name'],
                    'path' => $pdf['path'],
                    'display' => $pdf['display'],
                    'owned' => (bool) ($pdf['owned'] ?? true),
                ];
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $anexos;
    }

    /**
     * @return array{path: string, name: string, display: string}|null
     */
    private function storeDavPdfAttachment(ForcaVendasOrder $order): ?array
    {
        $empresaId = (int) ($order->empresa_id ?? ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id ?? 0);
        $empresa = $empresaId > 0 ? Empresa::query()->find($empresaId) : Auth::user()?->empresa;

        $impValorLiquido = filter_var(
            $empresa?->param_monitor_vendas_imp_valor_liquido ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
        $impSemColunaDesconto = filter_var(
            $empresa?->param_monitor_vendas_imp_sem_coluna_desconto ?? true,
            FILTER_VALIDATE_BOOLEAN,
        );

        $blocos = MonitorPedidosReport::buildBlocos(
            [(int) $order->id],
            $empresaId > 0 ? $empresaId : null,
            $impValorLiquido,
        );

        if ($blocos === []) {
            return null;
        }

        $data = [
            'empresa' => $empresa,
            'blocos' => $blocos,
            'reportTitle' => 'Pedido / DAV',
            'cargaCabecalho' => null,
            'printedAt' => now(),
            'printedBy' => (string) (Auth::user()?->name ?: Auth::user()?->email ?: 'USUARIO'),
            'empresaEndereco' => '',
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'impSemColunaDesconto' => $impSemColunaDesconto,
        ];

        $directory = storage_path('app/temp/forca-vendas-monitor-envio');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.'dav-'.$order->id.'-'.uniqid('', true).'.pdf';
        $name = 'DAV.PDF';

        Pdf::loadView('reports.monitor-pedidos-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }

    private function logoDataUri(?Empresa $empresa): ?string
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

    /**
     * @return list<Nfe>
     */
    private function nfesTransmitidas(ForcaVendasOrder $order): array
    {
        $vendaId = (int) ($order->venda_id ?? 0);

        if ($vendaId <= 0) {
            return [];
        }

        return Nfe::query()
            ->where('venda_id', $vendaId)
            ->whereIn('status', [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CONTINGENCIA])
            ->orderByDesc('id')
            ->limit(2)
            ->get()
            ->all();
    }

    /**
     * @return list<Boleto>
     */
    private function boletosDoPedido(ForcaVendasOrder $order): array
    {
        $base = 'FV-'.$order->id;

        $contaIds = ContaReceber::query()
            ->when($order->empresa_id, fn ($q) => $q->where('empresa_id', (int) $order->empresa_id))
            ->where(function ($q) use ($base): void {
                $q->where('documento', $base)
                    ->orWhere('documento', 'like', $base.'/%');
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($contaIds === []) {
            return [];
        }

        return Boleto::query()
            ->whereIn('conta_receber_id', $contaIds)
            ->where('status', '!=', Boleto::STATUS_CANCELADO)
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->all();
    }

    /**
     * @return array{path: string, name: string, display: string, owned?: bool}|null
     */
    private function storeBoletoPdf(Boleto $boleto, string $numeroDav): ?array
    {
        $saved = trim((string) ($boleto->path_pdf ?? ''));
        $nn = preg_replace('/\D+/', '', (string) ($boleto->nosso_numero ?: $boleto->id)) ?: (string) $boleto->id;

        if ($saved !== '' && is_file($saved)) {
            return [
                'path' => $saved,
                'name' => 'BOLETO-DAV-'.$numeroDav.'-'.$nn.'.pdf',
                'display' => 'Boleto '.$nn,
                'owned' => false,
            ];
        }

        $linha = trim((string) ($boleto->linha_digitavel ?? ''));
        $nnRaw = trim((string) ($boleto->nosso_numero ?? ''));

        if ($linha === '' && $nnRaw === '') {
            return null;
        }

        $directory = storage_path('app/temp/forca-vendas-monitor-envio');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.'boleto-'.$boleto->id.'-'.uniqid('', true).'.pdf';
        $name = 'BOLETO-DAV-'.$numeroDav.'-'.$nn.'.pdf';

        $html = '<html><body style="font-family:DejaVu Sans,sans-serif;font-size:12px;">'
            .'<h2>Boleto DAV '.$numeroDav.'</h2>'
            .'<p><strong>Nosso número:</strong> '.e($nnRaw !== '' ? $nnRaw : $nn).'</p>'
            .'<p><strong>Linha digitável:</strong> '.e($linha).'</p>'
            .'</body></html>';

        Pdf::loadHTML($html)->setPaper('a4', 'portrait')->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => 'Boleto '.($nnRaw !== '' ? $nnRaw : $nn),
        ];
    }
}
