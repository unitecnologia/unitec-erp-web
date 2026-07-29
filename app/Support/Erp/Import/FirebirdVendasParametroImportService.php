<?php

namespace App\Support\Erp\Import;

use App\Models\Empresa;
use App\Models\VendasParametro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdVendasParametroImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public function mapFirebirdRow(array $row, int $empresaId): ?array
    {
        $payload = [
            'empresa_id' => $empresaId,
            'uf' => Str::upper(trim((string) ($row['UF'] ?? $row['uf'] ?? ''))) ?: null,
            'ambiente' => (int) ($row['AMBIENTE'] ?? $row['ambiente'] ?? VendasParametro::AMBIENTE_HOMOLOGACAO),
            'versao_nfe' => filled($row['VERSAONFE'] ?? $row['versaonfe'] ?? null)
                ? (int) ($row['VERSAONFE'] ?? $row['versaonfe'])
                : null,
            'forma_emissao' => filled($row['FORMAEMISSAO'] ?? $row['formaemissao'] ?? null)
                ? (int) ($row['FORMAEMISSAO'] ?? $row['formaemissao'])
                : null,
            'tipo_emissao' => filled($row['TIPO_EMISSAO'] ?? $row['tipo_emissao'] ?? null)
                ? (int) ($row['TIPO_EMISSAO'] ?? $row['tipo_emissao'])
                : null,
            'caminho_certificado' => trim((string) ($row['CAMINHO_CERTIFICADO'] ?? $row['caminho_certificado'] ?? '')) ?: null,
            'senha_certificado' => trim((string) ($row['SENHACERTIFICADO'] ?? $row['senhacertificado'] ?? '')) ?: null,
            'numero_serie_certificado' => trim((string) ($row['NUMEROSERIECERTFICADO'] ?? $row['numeroseriecertficado'] ?? '')) ?: null,
            'crypt_lib' => filled($row['CRYPTLIB'] ?? null) ? (int) $row['CRYPTLIB'] : null,
            'http_lib' => filled($row['HTTPLIB'] ?? null) ? (int) $row['HTTPLIB'] : null,
            'xml_sign' => filled($row['XMLSIGN'] ?? null) ? (int) $row['XMLSIGN'] : null,
            'ssl_tipo' => filled($row['SSL_TIPO'] ?? null) ? (int) $row['SSL_TIPO'] : null,
            'aguardar' => filled($row['AGUARDAR'] ?? null) ? (int) $row['AGUARDAR'] : null,
            'tentativas' => filled($row['TENTATIVAS'] ?? null) ? (int) $row['TENTATIVAS'] : null,
            'intervalo' => filled($row['INTERVALO'] ?? null) ? (int) $row['INTERVALO'] : null,
            'ajustar_auto' => $this->snToBool($row['AJUSTARAUTO'] ?? 'S'),
            'proxy_host' => trim((string) ($row['PROXY_HOST'] ?? '')) ?: null,
            'proxy_porta' => filled($row['PROXY_PORTA'] ?? null) ? (int) $row['PROXY_PORTA'] : null,
            'proxy_usuario' => trim((string) ($row['PROXY_USUARIO'] ?? '')) ?: null,
            'proxy_senha' => trim((string) ($row['PROXY_SENHA'] ?? '')) ?: null,
            'path_salvar_nfe' => trim((string) ($row['PATHSALVARNFE'] ?? $row['PATHSALVAR'] ?? '')) ?: null,
            'path_schemas_nfe' => trim((string) ($row['PATHSCHEMAS_NFE'] ?? $row['PATHSCHEMAS'] ?? '')) ?: null,
            'path_enviada_nfe' => trim((string) ($row['PATHENVIADA_NFE'] ?? $row['PATHNFE'] ?? '')) ?: null,
            'path_can_nfe' => trim((string) ($row['PATHCAN_NFE'] ?? $row['PATHCAN'] ?? '')) ?: null,
            'path_inuti_nfe' => trim((string) ($row['PATHINUTI_NFE'] ?? $row['PATHINUTI'] ?? '')) ?: null,
            'path_evento_nfe' => trim((string) ($row['PATHEVENTO_NFE'] ?? $row['PATHEVENTO'] ?? '')) ?: null,
            'path_pdf_nfe' => trim((string) ($row['PATHPDF_NFE'] ?? $row['PATHPDF'] ?? '')) ?: null,
            'serie' => trim((string) ($row['SERIE'] ?? '1')) ?: '1',
            'serie_nfe' => filled($row['SERIE_NFE'] ?? null) ? (int) $row['SERIE_NFE'] : 1,
            'id_token' => trim((string) ($row['IDTOKEN'] ?? '')) ?: null,
            'token' => trim((string) ($row['TOKEN'] ?? '')) ?: null,
            'versao_qrcode' => filled($row['VERSAOQRCODE'] ?? null) ? (int) $row['VERSAOQRCODE'] : null,
            'email_host' => trim((string) ($row['EMAILHOST'] ?? '')) ?: null,
            'email_porta' => filled($row['EMAILPORTA'] ?? null) ? (int) $row['EMAILPORTA'] : null,
            'email_user' => trim((string) ($row['EMAILUSER'] ?? '')) ?: null,
            'email_senha' => trim((string) ($row['EMAILSENHA'] ?? '')) ?: null,
            'email_assunto' => trim((string) ($row['EMAILASSUNTO'] ?? '')) ?: null,
            'email_ssl' => $this->snToBool($row['EMAILSSL'] ?? 'N'),
            'email_tls' => $this->snToBool($row['EMAILTLS'] ?? 'N'),
        ];

        if (filled($row['NUMERO'] ?? null) && (int) $row['NUMERO'] > 0) {
            $payload['numero'] = (int) $row['NUMERO'];
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $empresaId = (int) (Empresa::query()->value('id') ?? 0);

        if ($empresaId < 1) {
            return ['created' => 0, 'updated' => 0, 'skipped' => count($rows)];
        }

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, $empresaId, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow($row, $empresaId);

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = VendasParametro::query()->find($empresaId);

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                if ($existing) {
                    $existing->fill($payload);
                    $existing->save();
                    $stats['updated']++;
                } else {
                    VendasParametro::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    protected function snToBool(mixed $value): bool
    {
        return in_array(Str::upper(trim((string) $value)), ['S', '1', 'T', 'TRUE', 'Y'], true);
    }
}
