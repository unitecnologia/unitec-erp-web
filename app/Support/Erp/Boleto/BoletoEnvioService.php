<?php

namespace App\Support\Erp\Boleto;

use App\Models\Boleto;
use App\Models\BoletoContaApi;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\Nfe;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use App\Models\PdvVenda;
use App\Models\Venda;
use App\Services\Sicredi\SicrediCobrancaClient;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Nfe\NfeDanfeReportService;
use App\Support\Erp\Orcamento\OrcamentoReportService;
use App\Support\Erp\Os\OrdemServicoReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * PDF do boleto + anexos relacionados (pedido/DAV, OS, NF-e) para impressão/envio.
 */
final class BoletoEnvioService
{
    public function __construct(
        private readonly NfeDanfeReportService $nfeDanfe = new NfeDanfeReportService,
        private readonly OrcamentoReportService $orcamentoReport = new OrcamentoReportService,
        private readonly OrdemServicoReportService $osReport = new OrdemServicoReportService,
    ) {}

    /**
     * @return list<array{id: string, name: string, path: string, display: string, owned?: bool, mimetype?: string}>
     */
    public function buildDispatchAttachments(Boleto $boleto, Empresa $empresa): array
    {
        $anexos = [];
        $pdf = $this->ensurePdf($boleto, $empresa);
        $anexos[] = [
            'id' => 'boleto-pdf',
            'name' => $pdf['name'],
            'path' => $pdf['path'],
            'display' => $pdf['display'],
            'owned' => (bool) ($pdf['owned'] ?? true),
            'mimetype' => 'application/pdf',
        ];

        $conta = $boleto->contaReceber
            ?? ($boleto->conta_receber_id
                ? ContaReceber::query()->with('cliente')->find($boleto->conta_receber_id)
                : null);

        if ($conta instanceof ContaReceber) {
            foreach ($this->anexosPedidoRelacionados($conta, $empresa) as $anexoPedido) {
                $anexos[] = $anexoPedido;
            }

            foreach ($this->nfesRelacionadas($conta) as $nfe) {
                try {
                    $danfe = $this->nfeDanfe->storePdfAttachment($nfe);
                    $anexos[] = [
                        'id' => 'nfe-pdf-'.$nfe->id,
                        'name' => $danfe['name'],
                        'path' => $danfe['path'],
                        'display' => 'DANFE NF-e '.$nfe->numero,
                        'owned' => true,
                        'mimetype' => 'application/pdf',
                    ];
                } catch (Throwable $e) {
                    report($e);
                }

                try {
                    $xml = $this->nfeDanfe->storeXmlAttachment($nfe);
                    if (is_array($xml)) {
                        $anexos[] = [
                            'id' => 'nfe-xml-'.$nfe->id,
                            'name' => $xml['name'],
                            'path' => $xml['path'],
                            'display' => $xml['display'] ?? $xml['name'],
                            'owned' => true,
                            'mimetype' => 'application/xml',
                        ];
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $anexos;
    }

    /**
     * Pedido FV (DAV/orçamento) ou OS vinculados ao documento da CR.
     *
     * @return list<array{id: string, name: string, path: string, display: string, owned: bool, mimetype: string}>
     */
    private function anexosPedidoRelacionados(ContaReceber $conta, Empresa $empresa): array
    {
        $anexos = [];
        $documento = trim((string) ($conta->documento ?? ''));

        if (preg_match('/^FV-(\d+)/', $documento, $m)) {
            $order = ForcaVendasOrder::query()->with('pedido')->find((int) $m[1]);

            if ($order?->pedido) {
                try {
                    $envio = app(\App\Support\ForcaVendas\ForcaVendasMonitorEnvioAnexosService::class);
                    $anexosDav = $envio->montar($order);
                    foreach ($anexosDav as $anexo) {
                        if (($anexo['id'] ?? '') !== 'dav-pdf') {
                            continue;
                        }
                        $numero = $envio->formatNumeroDav($order);
                        $anexos[] = [
                            'id' => 'pedido-fv-'.$order->id,
                            'name' => $anexo['name'],
                            'path' => $anexo['path'],
                            'display' => 'Pedido DAV '.$numero,
                            'owned' => true,
                            'mimetype' => 'application/pdf',
                        ];
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        if (preg_match('/^OS-(\d+)/', $documento, $m)) {
            $numeroOs = (string) ((int) $m[1]);
            $os = OrdemServico::query()
                ->when(
                    $conta->empresa_id,
                    fn ($q) => $q->where('empresa_id', (int) $conta->empresa_id),
                )
                ->where(function ($q) use ($numeroOs, $m): void {
                    $q->where('numero', $numeroOs)
                        ->orWhere('numero', (int) $m[1])
                        ->orWhere('numero', str_pad($m[1], 6, '0', STR_PAD_LEFT));
                })
                ->first();

            if ($os instanceof OrdemServico) {
                try {
                    $pdf = $this->osReport->storePdfAttachment($os, $empresa);
                    $rotulo = preg_replace('/\D/', '', (string) ($os->numero ?? '')) ?: (string) $os->id;
                    $anexos[] = [
                        'id' => 'pedido-os-'.$os->id,
                        'name' => 'OS-'.$rotulo.'.pdf',
                        'path' => $pdf['path'],
                        'display' => 'OS '.$rotulo,
                        'owned' => true,
                        'mimetype' => 'application/pdf',
                    ];
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $anexos;
    }

    /**
     * @return array{path: string, name: string, display: string, owned?: bool}
     */
    public function ensurePdf(Boleto $boleto, Empresa $empresa): array
    {
        $saved = trim((string) ($boleto->path_pdf ?? ''));
        if ($saved !== '' && is_file($saved)) {
            $nn = $this->nossoNumeroLabel($boleto);

            return [
                'path' => $saved,
                'name' => 'BOLETO-'.$nn.'.pdf',
                'display' => 'Boleto '.$nn,
                'owned' => false,
            ];
        }

        $directory = storage_path('app/temp/boletos-envio');
        File::ensureDirectoryExists($directory);
        $nn = $this->nossoNumeroLabel($boleto);
        $path = $directory.DIRECTORY_SEPARATOR.'boleto-'.$boleto->id.'-'.uniqid('', true).'.pdf';
        $name = 'BOLETO-'.$nn.'.pdf';

        $bancoPdf = $this->tryFetchBankPdf($boleto, $empresa);
        if ($bancoPdf !== null && $bancoPdf !== '') {
            file_put_contents($path, $bancoPdf);
            $boleto->forceFill(['path_pdf' => $path])->save();

            return [
                'path' => $path,
                'name' => $name,
                'display' => 'Boleto '.$nn,
                // Persistido em path_pdf — não apagar no cleanup do modal.
                'owned' => false,
            ];
        }

        // Ailos (e demais bancos sem PDF na API): monta ficha FEBRABAN local.
        $linha = preg_replace('/\D/', '', (string) ($boleto->linha_digitavel ?? '')) ?: '';
        $barras = preg_replace('/\D/', '', (string) ($boleto->codigo_barras ?? '')) ?: '';
        if (strlen($linha) === 47 || strlen($barras) === 44) {
            $rendered = app(BoletoLocalPdfRenderer::class)->saveTo($path, $boleto, $empresa);
            $boleto->forceFill(['path_pdf' => $path])->save();
            $rendered['owned'] = false;

            return $rendered;
        }

        Pdf::loadHTML($this->resumoHtml($boleto))->setPaper('a4', 'portrait')->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => 'Boleto '.$nn,
            'owned' => true,
        ];
    }

    public function defaultEmailSubject(Boleto $boleto): string
    {
        $nn = $this->nossoNumeroLabel($boleto);

        return 'Boleto nº '.$nn;
    }

    public function defaultEmailMessage(Boleto $boleto, ContaReceber $conta, string $clienteNome): string
    {
        $nome = trim($clienteNome) !== '' ? $clienteNome : 'cliente';
        $nn = $this->nossoNumeroLabel($boleto);
        $doc = trim((string) ($boleto->numero_documento ?: $conta->documento ?: $conta->numero)) ?: '—';
        $venc = optional($boleto->vencimento)?->format('d/m/Y') ?: '—';
        $valor = 'R$ '.ErpMoney::formatBr((float) ($boleto->valor ?? $conta->valor ?? 0));
        $linha = trim((string) ($boleto->linha_digitavel ?? ''));

        $msg = "Olá, {$nome}!\n\n"
            ."Segue em anexo o boleto para pagamento"
            .(preg_match('/^FV-/', trim((string) ($conta->documento ?? ''))) ? ' e o pedido (DAV)' : '')
            .(preg_match('/^OS-/', trim((string) ($conta->documento ?? ''))) ? ' e a ordem de serviço' : '')
            .".\n"
            ."Nosso número: {$nn}\n"
            ."Documento: {$doc}\n"
            ."Vencimento: {$venc}\n"
            ."Valor: {$valor}";

        if ($linha !== '') {
            $msg .= "\nLinha digitável: {$linha}";
        }

        return $msg;
    }

    public function sucessoDetalhe(Boleto $boleto): string
    {
        $nn = $this->nossoNumeroLabel($boleto);
        $linha = trim((string) ($boleto->linha_digitavel ?? ''));
        if ($linha !== '') {
            return 'Boleto '.$nn.' — Linha: '.$linha;
        }

        return 'Boleto '.$nn.' registrado no banco.';
    }

    /**
     * @return list<Nfe>
     */
    private function nfesRelacionadas(ContaReceber $conta): array
    {
        $vendaIds = [];
        $pdvIds = [];
        $documento = trim((string) ($conta->documento ?? ''));

        if (preg_match('/^FV-(\d+)/', $documento, $m)) {
            $order = ForcaVendasOrder::query()->find((int) $m[1]);
            if ($order?->venda_id) {
                $vendaIds[] = (int) $order->venda_id;
            }
        }

        if (preg_match('/^PDV-(\d+)$/', $documento, $m)) {
            $pdv = PdvVenda::query()->comercial()->where('numero', (int) $m[1])->first();
            if ($pdv) {
                $pdvIds[] = (int) $pdv->id;
                if ($pdv->venda_id) {
                    $vendaIds[] = (int) $pdv->venda_id;
                }
            }
        }

        if (preg_match('/^(?:VD|VENDA)\s*0*(\d+)/i', $documento, $m)) {
            $venda = Venda::query()
                ->where('numero', str_pad($m[1], 6, '0', STR_PAD_LEFT))
                ->first();
            if ($venda) {
                $vendaIds[] = (int) $venda->id;
            }
        }

        $vendaIds = array_values(array_unique(array_filter($vendaIds)));
        $pdvIds = array_values(array_unique(array_filter($pdvIds)));

        if ($vendaIds === [] && $pdvIds === []) {
            return [];
        }

        return Nfe::query()
            ->where('status', Nfe::STATUS_TRANSMITIDA)
            ->when(
                $conta->empresa_id,
                fn ($q) => $q->where('empresa_id', (int) $conta->empresa_id),
            )
            ->where(function ($q) use ($vendaIds, $pdvIds): void {
                if ($vendaIds !== []) {
                    $q->orWhereIn('venda_id', $vendaIds);
                }
                if ($pdvIds !== []) {
                    $q->orWhereIn('pdv_venda_id', $pdvIds);
                }
            })
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->all();
    }

    private function tryFetchBankPdf(Boleto $boleto, Empresa $empresa): ?string
    {
        $linha = preg_replace('/\D/', '', (string) ($boleto->linha_digitavel ?? '')) ?: '';
        if (strlen($linha) !== 47) {
            return null;
        }

        $banco = preg_replace('/\D/', '', (string) ($empresa->param_boleto_banco ?? '')) ?: '';
        if ($banco === '' && $boleto->boleto_conta_api_id) {
            $contaApi = BoletoContaApi::query()->find($boleto->boleto_conta_api_id);
            $banco = preg_replace('/\D/', '', (string) ($contaApi?->banco ?? '')) ?: '';
        }

        if ($banco !== EmpresaParametros::BOLETO_BANCO_SICREDI) {
            return null;
        }

        try {
            return app(SicrediCobrancaClient::class)->pdfBoleto($empresa, $linha);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function nossoNumeroLabel(Boleto $boleto): string
    {
        $nn = trim((string) ($boleto->nosso_numero ?? ''));

        return $nn !== '' ? $nn : (string) $boleto->id;
    }

    private function resumoHtml(Boleto $boleto): string
    {
        $nn = e($this->nossoNumeroLabel($boleto));
        $doc = e((string) ($boleto->numero_documento ?: '—'));
        $venc = e(optional($boleto->vencimento)?->format('d/m/Y') ?: '—');
        $valor = e('R$ '.ErpMoney::formatBr((float) ($boleto->valor ?? 0)));
        $linha = e(trim((string) ($boleto->linha_digitavel ?? '—')));
        $sacado = e(mb_strtoupper(trim((string) ($boleto->sacado_nome ?? '')), 'UTF-8') ?: '—');
        $pix = trim((string) ($boleto->pix_copia_cola ?? ''));
        $pixHtml = $pix !== ''
            ? '<p><strong>PIX Copia e Cola:</strong><br><span style="font-size:11px;word-break:break-all;">'.e($pix).'</span></p>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:13px;color:#0f172a;margin:28px;}
h1{font-size:18px;margin:0 0 12px;color:#1e3a8a;}
.box{border:1px solid #cbd5e1;border-radius:8px;padding:14px 16px;margin-top:12px;}
.label{color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.03em;}
.value{font-weight:700;margin:2px 0 10px;}
.linha{font-size:14px;letter-spacing:.04em;font-weight:700;word-break:break-all;}
</style>
</head>
<body>
<h1>Boleto</h1>
<div class="box">
<div class="label">Sacado</div><div class="value">{$sacado}</div>
<div class="label">Nosso número</div><div class="value">{$nn}</div>
<div class="label">Documento</div><div class="value">{$doc}</div>
<div class="label">Vencimento</div><div class="value">{$venc}</div>
<div class="label">Valor</div><div class="value">{$valor}</div>
<div class="label">Linha digitável</div>
<div class="linha">{$linha}</div>
{$pixHtml}
</div>
</body>
</html>
HTML;
    }
}
