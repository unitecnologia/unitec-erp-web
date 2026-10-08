<?php

namespace App\Support\Fiscal;

use App\Models\PdvVendaNfce;

/**
 * Confere se chave, XML assinado e QR Code da NFC-e em contingência descrevem o mesmo documento
 * (o DANFE impresso precisa ser exatamente o que será transmitido).
 */
final class NfceContingenciaConsistencia
{
    /** null = consistente; string = motivo da inconsistência. */
    public static function motivo(PdvVendaNfce $nfce): ?string
    {
        $chave = (string) $nfce->chave;

        if (preg_match('/^\d{44}$/', $chave) !== 1) {
            return 'chave de acesso ausente ou inválida';
        }

        if ($chave[34] !== '9') {
            return 'a chave não é de emissão em contingência (tpEmis 9)';
        }

        if ((int) substr($chave, 25, 9) !== (int) $nfce->numero) {
            return 'a chave não corresponde ao número da nota';
        }

        if ((int) substr($chave, 22, 3) !== PdvNfceEmissionService::serieInt($nfce)) {
            return 'a chave não corresponde à série da nota';
        }

        if (filled($nfce->cnf) && substr($chave, 35, 8) !== str_pad((string) $nfce->cnf, 8, '0', STR_PAD_LEFT)) {
            return 'a chave não corresponde ao código numérico (cNF) gravado';
        }

        $nfe = NfceXmlProtocolo::nfe($nfce->xml);

        if ($nfe === null) {
            return 'XML assinado da contingência não está gravado';
        }

        if (! str_contains($nfe, 'Id="NFe'.$chave.'"')) {
            return 'o XML gravado pertence a outra chave';
        }

        if (NfceXmlProtocolo::tpEmis($nfe) !== 9) {
            return 'o XML gravado não está em contingência (tpEmis)';
        }

        $qr = trim((string) $nfce->qr_code_conteudo);

        if ($qr === '' || ! str_contains($qr, $chave)) {
            return 'o QR Code gravado não corresponde à chave';
        }

        $qrXml = NfceXmlProtocolo::qrCodeDoXml($nfe);

        if ($qrXml !== '' && $qrXml !== $qr) {
            return 'o QR Code gravado difere do QR Code do XML assinado';
        }

        // QR v2 offline carrega o digVal (hex do DigestValue em base64) do XML assinado.
        if (str_contains($qr, $chave.'|2|')) {
            $digest = NfceXmlProtocolo::digestValue($nfe);

            if ($digest === '' || ! str_contains(strtolower($qr), strtolower(bin2hex($digest)))) {
                return 'o QR Code não corresponde à assinatura do XML (digVal)';
            }
        }

        return null;
    }
}
