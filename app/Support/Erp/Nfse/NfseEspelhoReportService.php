<?php

namespace App\Support\Erp\Nfse;

use App\Models\Nfse;
use App\Support\Erp\Nfse\Ipm\NfseIpmImpressaoViewData;
use App\Support\Erp\Nfse\NfseImpressao;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

final class NfseEspelhoReportService
{
    /**
     * @return array<string, mixed>
     */
    public function buildViewData(Nfse $nfse, bool $autoPrint = false, bool $embedded = false): array
    {
        if (NfseIpmImpressaoViewData::aplica($nfse)) {
            $data = NfseIpmImpressaoViewData::for($nfse, $autoPrint, $embedded);
            $data['espelho'] = true;
            $data['ipm']['espelho'] = true;
            $data['ipm']['numero'] = '-';
            $data['ipm']['identificador'] = '-';
            $data['ipm']['chave_acesso'] = '-';
            $data['ipm']['qr_data_uri'] = null;
            $data['ipm']['situacao'] = 'Não emitida';

            return $data;
        }

        $data = NfseDanfseViewData::for($nfse, autoPrint: $autoPrint, embedded: $embedded);
        $data['impressao_view'] = NfseImpressao::VIEW_NACIONAL;

        $data['espelho'] = true;
        $data['danfse']['versao'] = 'ESPELHO DA NFS-e';
        $data['danfse']['subtitulo'] = 'Espelho sem validade fiscal — apenas conferência';
        $data['danfse']['sem_validade_juridica'] = true;
        $data['danfse']['aviso_espelho'] = 'ESPELHO DA NFS-e — SEM VALIDADE FISCAL';
        $data['danfse']['aviso_espelho_sub'] = 'Documento apenas para conferência. Não substitui a NFS-e autorizada pelo Ambiente Nacional.';
        $data['danfse']['chave'] = '-';
        $data['danfse']['chave_formatada'] = '-';
        $data['danfse']['numero_nfse'] = '-';
        $data['danfse']['qr_url'] = null;
        $data['danfse']['qr_data_uri'] = null;
        $data['danfse']['qr_mensagem'] = 'Espelho sem validade fiscal — sem QR Code oficial.';

        return $data;
    }

    /**
     * @return array{path: string, name: string, display: string}
     */
    public function storePdfAttachment(Nfse $nfse): array
    {
        $data = $this->buildViewData($nfse, embedded: true);
        $directory = storage_path('app/temp/nfse');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.'espelho-nfse-'.$nfse->id.'-'.uniqid('', true).'.pdf';
        $numero = preg_replace('/\D+/', '', (string) ($nfse->numero_dps ?: $nfse->id)) ?: (string) $nfse->id;
        $name = 'ESPELHO-NFSE-'.$numero.'.PDF';

        Pdf::loadView((string) ($data['impressao_view'] ?? NfseImpressao::VIEW_NACIONAL), $data)
            ->setPaper('a4', 'portrait')
            ->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }
}
