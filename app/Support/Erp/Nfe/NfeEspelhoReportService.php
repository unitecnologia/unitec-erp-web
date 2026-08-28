<?php

namespace App\Support\Erp\Nfe;

use App\Models\Empresa;
use App\Models\Nfe;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

final class NfeEspelhoReportService
{
    public function __construct(
        private readonly NfeDanfeReportService $danfe = new NfeDanfeReportService(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(Nfe $nfe, ?Empresa $empresa = null): array
    {
        $data = $this->danfe->buildViewData($nfe, $empresa);

        $data['espelho'] = true;
        $data['documentoTitulo'] = 'ESPELHO DA NF-e';
        $data['documentoSubtitulo'] = 'Espelho sem validade fiscal — apenas conferência';
        $data['avisoEspelho'] = 'ESPELHO DA NF-e — SEM VALIDADE FISCAL';
        $data['protocolo'] = '';
        $data['chave'] = '';
        $data['chaveFormatada'] = '—';
        $data['barcodeDataUri'] = null;

        return $data;
    }

    /**
     * @return array{path: string, name: string, display: string}
     */
    public function storePdfAttachment(Nfe $nfe, ?Empresa $empresa = null): array
    {
        $data = $this->buildViewData($nfe, $empresa);
        $directory = storage_path('app/temp/nfe');

        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . 'espelho-nfe-' . $nfe->id . '-' . uniqid('', true) . '.pdf';
        $numeroDigits = preg_replace('/\D/', '', (string) $nfe->numero) ?: (string) $nfe->id;
        $name = 'ESPELHO-NFE-' . $numeroDigits . '.PDF';

        Pdf::loadView('reports.nfe-espelho-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }

    public function defaultWhatsAppMessage(Nfe $nfe, string $destinatario = 'cliente'): string
    {
        $nfe = $this->danfe->loadNfe($nfe);
        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;
        $total = number_format((float) $nfe->total, 2, ',', '.');
        $destino = $destinatario === 'fornecedor' ? 'fornecedor/transportadora' : 'cliente';

        $lines = [
            "Olá! Segue o espelho da NF-e em elaboração (sem valor fiscal) para conferência do {$destino}:",
            "Nota: {$numero} | Série: {$nfe->serie}",
            'Situação: ABERTA — documento sem validade fiscal.',
            "Valor previsto: R$ {$total}",
        ];

        return implode("\n", $lines);
    }

    public function defaultEmailSubject(Nfe $nfe): string
    {
        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;

        return 'Espelho NF-e nº ' . $numero . ' — sem valor fiscal';
    }

    public function defaultEmailMessage(Nfe $nfe, string $destinatarioNome = ''): string
    {
        $saudacao = filled($destinatarioNome)
            ? 'Olá, ' . $destinatarioNome . '!'
            : 'Olá!';

        return $saudacao . "\n\n"
            . "Segue em anexo o espelho da NF-e em elaboração para sua conferência.\n"
            . "Este documento está em aberto e não possui valor fiscal.\n\n"
            . 'Atenciosamente.';
    }
}
