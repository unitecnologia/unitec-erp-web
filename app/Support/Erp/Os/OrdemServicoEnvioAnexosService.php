<?php

namespace App\Support\Erp\Os;

use App\Models\Boleto;
use App\Models\ContaReceber;
use App\Models\Nfe;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Nfe\NfeDanfeReportService;
use App\Support\Erp\Nfse\NfseImpressao;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Anexos do envio F9 da OS: OS + NFS-e + NF-e + boletos (quando existirem).
 */
final class OrdemServicoEnvioAnexosService
{
    public function __construct(
        private readonly OrdemServicoReportService $osReport = new OrdemServicoReportService,
        private readonly NfeDanfeReportService $nfeDanfe = new NfeDanfeReportService,
    ) {}

    /**
     * @return list<array{id: string, name: string, path: string, display: string}>
     */
    public function montar(OrdemServico $ordem, bool $tecnica = false): array
    {
        $anexos = [];
        $numero = $this->osReport->formatNumero($ordem);

        if ($tecnica) {
            try {
                $pdf = $this->osReport->storePdfAttachment($ordem, leveParaEnvio: true, tecnica: true);
                $anexos[] = [
                    'id' => 'os-tecnica-pdf',
                    'name' => $pdf['name'],
                    'path' => $pdf['path'],
                    'display' => 'OS Técnica '.$numero,
                ];
            } catch (Throwable $exception) {
                report($exception);
            }

            return $anexos;
        }

        try {
            $pdf = $this->osReport->storePdfAttachment($ordem, leveParaEnvio: true);
            $anexos[] = [
                'id' => 'os-pdf',
                'name' => $pdf['name'],
                'path' => $pdf['path'],
                'display' => 'OS '.$numero,
            ];
        } catch (Throwable $exception) {
            report($exception);
        }

        foreach ($this->nfsesAutorizadas($ordem) as $nfse) {
            try {
                $pdf = $this->storeNfsePdf($nfse);
                $anexos[] = [
                    'id' => 'nfse-pdf-'.$nfse->id,
                    'name' => $pdf['name'],
                    'path' => $pdf['path'],
                    'display' => $pdf['display'],
                ];
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                $xml = $this->storeNfseXml($nfse);
                if ($xml !== null) {
                    $anexos[] = [
                        'id' => 'nfse-xml-'.$nfse->id,
                        'name' => $xml['name'],
                        'path' => $xml['path'],
                        'display' => $xml['display'],
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        foreach ($this->nfesTransmitidas($ordem) as $nfe) {
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

        foreach ($this->boletosDaOs($ordem) as $boleto) {
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
     * @return list<Nfse>
     */
    private function nfsesAutorizadas(OrdemServico $ordem): array
    {
        if (! $ordem->relationLoaded('nfse')) {
            $ordem->load('nfse');
        }

        $linked = $ordem->nfse;

        if ($linked instanceof Nfse
            && $linked->status === Nfse::STATUS_AUTORIZADA
            && filled($linked->xml_nfse)
        ) {
            return [$linked];
        }

        return Nfse::query()
            ->where('ordem_servico_id', (int) $ordem->id)
            ->where('status', Nfse::STATUS_AUTORIZADA)
            ->whereNotNull('xml_nfse')
            ->orderByDesc('id')
            ->limit(1)
            ->get()
            ->all();
    }

    /**
     * @return list<Nfe>
     */
    private function nfesTransmitidas(OrdemServico $ordem): array
    {
        $numero = $this->osReport->formatNumero($ordem);

        if ($numero === '') {
            return [];
        }

        $query = Nfe::query()
            ->where('status', Nfe::STATUS_TRANSMITIDA)
            ->where(function ($q) use ($numero): void {
                $q->where('npedido', $numero)
                    ->orWhere('npedido', ltrim($numero, '0'))
                    ->orWhere('npedido', 'OS-'.$numero)
                    ->orWhere('npedido', 'OS '.$numero);
            })
            ->when(
                $ordem->empresa_id,
                fn ($q) => $q->where('empresa_id', (int) $ordem->empresa_id),
            )
            ->when(
                $ordem->cliente_id,
                fn ($q) => $q->where('cliente_id', (int) $ordem->cliente_id),
            )
            ->orderByDesc('id')
            ->limit(1);

        return $query->get()->all();
    }

    /**
     * @return list<Boleto>
     */
    private function boletosDaOs(OrdemServico $ordem): array
    {
        if (OrdemServicoFinanceiroVinculo::documentoBase($ordem) === null) {
            return [];
        }

        $contaIds = OrdemServicoFinanceiroVinculo::aplicar(ContaReceber::query(), $ordem)
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
     * @return array{path: string, name: string, display: string}
     */
    private function storeNfsePdf(Nfse $nfse): array
    {
        $data = NfseImpressao::dados($nfse, autoPrint: false, embedded: true);
        $directory = storage_path('app/temp/ordens-servico-envio');
        File::ensureDirectoryExists($directory);

        $numero = preg_replace('/\D+/', '', (string) ($nfse->numero_nfse ?: $nfse->numero_dps)) ?: (string) $nfse->id;
        $ipm = ($data['impressao_view'] ?? '') === NfseImpressao::VIEW_IPM;
        $path = $directory.DIRECTORY_SEPARATOR.'danfse-'.$nfse->id.'-'.uniqid('', true).'.pdf';
        $name = ($ipm ? 'NFSe-' : 'DANFSe-').$numero.'.pdf';

        Pdf::loadView((string) ($data['impressao_view'] ?? NfseImpressao::VIEW_NACIONAL), $data)
            ->setPaper('a4', 'portrait')
            ->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => ($ipm ? 'NFS-e ' : 'DANFSe NFS-e ').$numero,
        ];
    }

    /**
     * @return array{path: string, name: string, display: string}|null
     */
    private function storeNfseXml(Nfse $nfse): ?array
    {
        $xml = trim((string) ($nfse->xml_nfse ?? ''));

        if ($xml === '') {
            return null;
        }

        $directory = storage_path('app/temp/ordens-servico-envio');
        File::ensureDirectoryExists($directory);

        $numero = preg_replace('/\D+/', '', (string) ($nfse->numero_nfse ?: $nfse->numero_dps)) ?: (string) $nfse->id;
        $path = $directory.DIRECTORY_SEPARATOR.'nfse-'.$nfse->id.'-'.uniqid('', true).'.xml';
        $name = 'NFSe-'.$numero.'.xml';
        file_put_contents($path, $xml);

        return [
            'path' => $path,
            'name' => $name,
            'display' => 'XML NFS-e '.$numero,
        ];
    }

    /**
     * @return array{path: string, name: string, display: string, owned?: bool}|null
     */
    private function storeBoletoPdf(Boleto $boleto, string $numeroOs): ?array
    {
        $saved = trim((string) ($boleto->path_pdf ?? ''));

        if ($saved !== '' && is_file($saved)) {
            $nn = preg_replace('/\D+/', '', (string) ($boleto->nosso_numero ?: $boleto->id)) ?: (string) $boleto->id;

            return [
                'path' => $saved,
                'name' => 'BOLETO-OS-'.$numeroOs.'-'.$nn.'.pdf',
                'display' => 'Boleto '.$nn,
                'owned' => false,
            ];
        }

        $linha = trim((string) ($boleto->linha_digitavel ?? ''));
        $nn = trim((string) ($boleto->nosso_numero ?? ''));

        if ($linha === '' && $nn === '') {
            return null;
        }

        $directory = storage_path('app/temp/ordens-servico-envio');
        File::ensureDirectoryExists($directory);

        $suffix = preg_replace('/\D+/', '', $nn !== '' ? $nn : (string) $boleto->id) ?: (string) $boleto->id;
        $path = $directory.DIRECTORY_SEPARATOR.'boleto-'.$boleto->id.'-'.uniqid('', true).'.pdf';
        $name = 'BOLETO-OS-'.$numeroOs.'-'.$suffix.'.pdf';

        $html = $this->boletoResumoHtml($boleto, $numeroOs);
        Pdf::loadHTML($html)->setPaper('a4', 'portrait')->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => 'Boleto '.($nn !== '' ? $nn : $suffix),
        ];
    }

    private function boletoResumoHtml(Boleto $boleto, string $numeroOs): string
    {
        $nn = e((string) ($boleto->nosso_numero ?: '—'));
        $doc = e((string) ($boleto->numero_documento ?: '—'));
        $venc = e(optional($boleto->vencimento)?->format('d/m/Y') ?: '—');
        $valor = e('R$ '.ErpMoney::formatBr((float) ($boleto->valor ?? 0)));
        $linha = e(trim((string) ($boleto->linha_digitavel ?? '—')));
        $sacado = e(mb_strtoupper(trim((string) ($boleto->sacado_nome ?? '')), 'UTF-8') ?: '—');
        $os = e($numeroOs);
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
<h1>Boleto — OS {$os}</h1>
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
<p style="margin-top:18px;color:#64748b;font-size:11px;">Documento gerado pelo Unitec ERP para envio junto à Ordem de Serviço.</p>
</body>
</html>
HTML;
    }
}
