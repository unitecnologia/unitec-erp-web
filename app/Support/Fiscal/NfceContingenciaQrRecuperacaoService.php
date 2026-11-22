<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Nfce\NfceQrCodeBuilder;
use Unitec\FiscalEngine\Xml\NfceXmlBuilder;
use Unitec\FiscalEngine\Xml\XmlSigner;

/**
 * Regenera QR Code de NFC-e em contingência com Id/chave tpEmis=9 íntegros,
 * quando o QR importado (SCV) aponta para chave tpEmis=1 ou está vazio.
 *
 * Não reassinatura: só altera infNFeSupl (fora do digest de infNFe).
 * Não transmite. Pronto para F5 só se SEFAZ confirmar que a chave não está autorizada.
 */
final class NfceContingenciaQrRecuperacaoService
{
    public function __construct(
        private readonly NfceQrCodeBuilder $qrBuilder = new NfceQrCodeBuilder(),
        private readonly NfceXmlBuilder $xmlBuilder = new NfceXmlBuilder(),
        private readonly XmlSigner $signer = new XmlSigner(),
        private readonly PdvNfceConsultaService $consulta = new PdvNfceConsultaService(),
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     nfce_id: int,
     *     numero: string,
     *     serie: string,
     *     chave: string,
     *     backup_path: ?string,
     *     assinatura_ok: bool,
     *     sefaz_consultado: bool,
     *     sefaz_cStat: ?string,
     *     sefaz_motivo: ?string,
     *     sefaz_autorizada: bool,
     *     qr_antes: string,
     *     qr_depois: string,
     *     consistencia: ?string,
     *     pronto_transmitir: bool,
     *     mensagem: string
     * }
     */
    public function corrigirUma(PdvVendaNfce $nfce, Empresa $empresa, VendasParametro $parametros, string $backupDir, bool $aplicar = true): array
    {
        $base = [
            'ok' => false,
            'nfce_id' => (int) $nfce->id,
            'numero' => (string) $nfce->numero,
            'serie' => (string) $nfce->serie,
            'chave' => (string) $nfce->chave,
            'backup_path' => null,
            'assinatura_ok' => false,
            'sefaz_consultado' => false,
            'sefaz_cStat' => null,
            'sefaz_motivo' => null,
            'sefaz_autorizada' => false,
            'qr_antes' => trim((string) $nfce->qr_code_conteudo) ?: NfceXmlProtocolo::qrCodeDoXml(NfceXmlProtocolo::nfe($nfce->xml) ?? ''),
            'qr_depois' => '',
            'consistencia' => null,
            'pronto_transmitir' => false,
            'mensagem' => '',
        ];

        if ((bool) $nfce->simulada || (string) $nfce->status === PdvVendaNfce::STATUS_SIMULADA) {
            return $this->fail($base, 'NFC-e simulada — recuperação não se aplica.');
        }

        if ((string) $nfce->status === PdvVendaNfce::STATUS_AUTORIZADA) {
            return $this->fail($base, 'NFC-e já autorizada — não alterar.');
        }

        if ((string) $nfce->status !== PdvVendaNfce::STATUS_CONTINGENCIA) {
            return $this->fail($base, 'Somente contingência é elegível (status='.$nfce->status.').');
        }

        $chave = (string) $nfce->chave;
        if (preg_match('/^\d{44}$/', $chave) !== 1 || $chave[34] !== '9') {
            return $this->fail($base, 'Chave inválida ou não é contingência (tpEmis≠9).');
        }

        $nfeXml = NfceXmlProtocolo::nfe($nfce->xml);
        if ($nfeXml === null) {
            return $this->fail($base, 'XML NFe ausente.');
        }

        if (! str_contains($nfeXml, 'Id="NFe'.$chave.'"')) {
            return $this->fail($base, 'Id do XML difere da chave gravada — abortado.');
        }

        if (NfceXmlProtocolo::tpEmis($nfeXml) !== 9) {
            return $this->fail($base, 'XML não está com tpEmis=9 — abortado.');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (! @$dom->loadXML($nfeXml)) {
            return $this->fail($base, 'XML não pôde ser carregado.');
        }

        // 1) Assinatura (chave pública do próprio XML — não exige senha do PFX).
        try {
            $certFromXml = $this->certificateFromXmlSignature($dom);
            $this->signer->assertSignatureIsValid($dom, $certFromXml);
            $base['assinatura_ok'] = true;
        } catch (FiscalEngineException $e) {
            return $this->fail($base, 'Assinatura inválida: '.$e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail($base, 'Falha ao validar assinatura: '.$e->getMessage());
        }

        // 2) Consulta SEFAZ — obrigatória para liberar transmissão.
        $sefazOkNaoAutorizada = false;
        try {
            $resp = $this->consulta->consultarChave($chave, $empresa, $parametros);
            $base['sefaz_consultado'] = true;
            $base['sefaz_cStat'] = $resp->statusCodigo;
            $base['sefaz_motivo'] = $resp->statusMotivo;
            $base['sefaz_autorizada'] = (bool) $resp->autorizada;

            if ($resp->autorizada || $resp->cancelada) {
                return $this->fail(
                    $base,
                    'SEFAZ indica '.($resp->autorizada ? 'autorizada' : 'cancelada')
                    .' (cStat '.$resp->statusCodigo.') — não regenerar QR nem transmitir por este fluxo.',
                );
            }

            // Não consta / rejeições conhecidas = elegível para corrigir QR e (depois) F5.
            if (in_array($resp->statusCodigo, PdvNfceConsultaService::CSTAT_LIBERA_REENVIO, true)
                || (! $resp->autorizada && ! $resp->cancelada && ! $resp->denegada)) {
                $sefazOkNaoAutorizada = true;
            }
        } catch (\Throwable $e) {
            $base['sefaz_motivo'] = $e->getMessage();
            // Sem consulta: pode corrigir QR para consistência local, mas NÃO libera transmissão.
            $sefazOkNaoAutorizada = false;
        }

        $idToken = trim((string) ($parametros->id_token ?? ''));
        $csc = trim((string) ($parametros->token ?? ''));
        if ($idToken === '' || $csc === '') {
            return $this->fail($base, 'CSC / ID Token da NFC-e não configurados na empresa.');
        }

        $digest = NfceXmlProtocolo::digestValue($nfeXml);
        if ($digest === '') {
            return $this->fail($base, 'DigestValue ausente no XML.');
        }

        $dhEmi = $this->tag($nfeXml, 'dhEmi');
        $vNf = (float) $this->tag($nfeXml, 'vNF');
        $tpAmb = (int) ($this->tag($nfeXml, 'tpAmb') ?: NfceFiscalCertificateResolver::tpAmb($parametros));
        $versaoQr = max(2, (int) ($parametros->versao_qrcode ?: 2));

        if ($dhEmi === '' || $vNf <= 0) {
            return $this->fail($base, 'dhEmi/vNF ausentes ou inválidos no XML — abortado.');
        }

        // 3) Novo QR (versão 2 offline / tpEmis 9) com a chave correta do Id.
        $qrNovo = $this->qrBuilder->buildUrl(
            chave: $chave,
            tpAmb: $tpAmb,
            tpEmis: 9,
            versaoQrcode: $versaoQr,
            idToken: $idToken,
            csc: $csc,
            dhEmiIso: $dhEmi,
            valorNota: $vNf,
            digestValueBase64: $digest,
        );

        if (! str_contains($qrNovo, $chave) || ! str_contains($qrNovo, '|2|')) {
            return $this->fail($base, 'QR gerado inválido.');
        }

        $base['qr_depois'] = $qrNovo;

        // 4) Backup completo antes de gravar.
        $backupPath = rtrim($backupDir, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'nfce-'.$nfce->id.'-n'.$nfce->numero.'.json';
        @mkdir($backupDir, 0777, true);
        file_put_contents($backupPath, json_encode([
            'backed_up_at' => date('c'),
            'nfce_id' => (int) $nfce->id,
            'numero' => $nfce->numero,
            'serie' => $nfce->serie,
            'chave' => $chave,
            'status' => $nfce->status,
            'qr_code_conteudo' => $nfce->qr_code_conteudo,
            'xml' => $nfce->xml,
            'cnf' => $nfce->cnf,
            'operacao' => $nfce->operacao,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $base['backup_path'] = $backupPath;

        if (! $aplicar) {
            $base['ok'] = true;
            $base['mensagem'] = 'Dry-run: QR seria regenerado; nada gravado.';
            $base['pronto_transmitir'] = false;

            return $base;
        }

        // 5) Atualiza infNFeSupl/qrCode no XML (sem tocar em Signature/infNFe).
        $xmlNovo = $this->substituirQrNoXml($dom, $qrNovo, $tpAmb);

        // Revalida assinatura após troca do QR (infNFe intacto).
        $dom2 = new DOMDocument();
        $dom2->preserveWhiteSpace = true;
        $dom2->formatOutput = false;
        if (! @$dom2->loadXML($xmlNovo)) {
            return $this->fail($base, 'XML pós-QR inválido.');
        }
        try {
            $this->signer->assertSignatureIsValid($dom2, $this->certificateFromXmlSignature($dom2));
        } catch (\Throwable $e) {
            return $this->fail($base, 'Assinatura quebrou após troca do QR (não gravado): '.$e->getMessage());
        }

        DB::transaction(function () use ($nfce, $xmlNovo, $qrNovo): void {
            $nfce->forceFill([
                'xml' => $xmlNovo,
                'qr_code_conteudo' => $qrNovo,
            ])->save();
        });

        $nfce = $nfce->fresh() ?? $nfce;
        $motivo = NfceContingenciaConsistencia::motivo($nfce);
        $base['consistencia'] = $motivo;

        if ($motivo !== null) {
            // Rollback a partir do backup.
            $payload = json_decode((string) file_get_contents($backupPath), true);
            if (is_array($payload)) {
                $nfce->forceFill([
                    'xml' => $payload['xml'] ?? $nfce->xml,
                    'qr_code_conteudo' => $payload['qr_code_conteudo'] ?? null,
                ])->save();
            }

            return $this->fail($base, 'Consistência falhou após correção (rollback feito): '.$motivo);
        }

        $base['ok'] = true;
        $base['pronto_transmitir'] = $sefazOkNaoAutorizada && $base['sefaz_consultado'] && ! $base['sefaz_autorizada'];
        $base['mensagem'] = $base['pronto_transmitir']
            ? 'QR regenerado e consistente. Elegível para F5 manual (SEFAZ confirmou ausência de autorização).'
            : 'QR regenerado e consistente localmente. Transmissão NÃO liberada: SEFAZ não consultada ou retorno inconclusivo — consulte antes do F5.';

        return $base;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function fail(array $base, string $mensagem): array
    {
        $base['ok'] = false;
        $base['mensagem'] = $mensagem;
        $base['pronto_transmitir'] = false;

        return $base;
    }

    private function certificateFromXmlSignature(DOMDocument $dom): Certificate
    {
        $x509 = $dom->getElementsByTagName('X509Certificate')->item(0);
        if ($x509 === null || trim((string) $x509->textContent) === '') {
            throw new FiscalEngineException('X509Certificate ausente no XML.');
        }

        $der = preg_replace('/\s+/', '', (string) $x509->textContent) ?? '';
        $pem = "-----BEGIN CERTIFICATE-----\n"
            .chunk_split($der, 64, "\n")
            ."-----END CERTIFICATE-----\n";

        // Só chave pública — suficiente para assertSignatureIsValid.
        return new Certificate('', $pem, '');
    }

    private function substituirQrNoXml(DOMDocument $dom, string $qrUrl, int $tpAmb): string
    {
        $nfe = $dom->getElementsByTagName('NFe')->item(0);
        if (! $nfe instanceof DOMElement) {
            throw new FiscalEngineException('NFe não encontrada.');
        }

        $supl = $dom->getElementsByTagName('infNFeSupl')->item(0);
        if ($supl instanceof DOMElement) {
            $nfe->removeChild($supl);
        }

        $this->xmlBuilder->appendQrCode($dom, $qrUrl, $tpAmb);

        $xml = $dom->saveXML();
        if (! is_string($xml) || $xml === '') {
            throw new FiscalEngineException('Falha ao serializar XML com novo QR.');
        }

        // Remove declaração XML se o armazenamento original costuma ser fragmento NFe.
        if (str_starts_with(ltrim((string) ($nfe->ownerDocument?->documentElement?->nodeName ?? '')), 'NFe')
            || preg_match('/^<\?xml/', trim($xml)) === 1) {
            // Mantém documento completo se root for NFe.
        }

        // Preferir só o bloco <NFe>...</NFe> como no banco.
        if (preg_match('/<NFe\b[\s\S]*?<\/NFe>/', $xml, $m) === 1) {
            return $m[0];
        }

        return $xml;
    }

    private function tag(string $xml, string $name): string
    {
        return preg_match('/<'.$name.'>([^<]*)<\/'.$name.'>/', $xml, $m) === 1
            ? trim($m[1])
            : '';
    }
}
