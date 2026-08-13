<?php

namespace App\Support\Erp\Import;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdPdvNfceImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $pdvVendaIdByNumero  numero => id
     * @param  array<string, int>  $empresaIdByCodigo
     * @return array<string, mixed>|null
     */
    public function mapFirebirdRow(array $row, array $pdvVendaIdByNumero, array $empresaIdByCodigo, ?int $fallbackEmpresaId): ?array
    {
        $fkVenda = (int) ($row['FK_VENDA'] ?? $row['fk_venda'] ?? 0);

        if ($fkVenda < 1) {
            return null;
        }

        $pdvVendaId = $pdvVendaIdByNumero[$fkVenda] ?? null;

        if (! $pdvVendaId) {
            return null;
        }

        $situacao = Str::upper(trim((string) ($row['SITUACAO'] ?? $row['situacao'] ?? 'T')));
        $status = $this->mapStatus($situacao);
        $emissao = $this->mapDateTime(
            $row['DATA_EMISSAO'] ?? $row['data_emissao'] ?? null,
            $row['HORA_EMISSAO'] ?? $row['hora_emissao'] ?? null,
        );

        $fkEmpresa = trim((string) ($row['FKEMPRESA'] ?? $row['fkempresa'] ?? ''));
        $empresaId = $fkEmpresa !== ''
            ? ($empresaIdByCodigo[$fkEmpresa] ?? $empresaIdByCodigo[(string) (int) $fkEmpresa] ?? $fallbackEmpresaId)
            : $fallbackEmpresaId;

        $chave = preg_replace('/\D/', '', (string) ($row['CHAVE'] ?? $row['chave'] ?? '')) ?: null;
        $protocolo = trim((string) ($row['PROTOCOLO'] ?? $row['protocolo'] ?? '')) ?: null;
        $motivoCancel = trim((string) ($row['MOTIVOCANCELAMENTO'] ?? $row['motivocancelamento'] ?? '')) ?: null;

        $numero = filled($row['NUMERO'] ?? null) ? (int) $row['NUMERO'] : null;
        $cnf = trim((string) ($row['CNF'] ?? $row['cnf'] ?? '')) ?: null;
        $serie = trim((string) ($row['SERIE'] ?? $row['serie'] ?? '1')) ?: '1';
        $modelo = trim((string) ($row['MODELO'] ?? $row['modelo'] ?? '65')) ?: '65';

        $autorizadaEm = in_array($status, [PdvVendaNfce::STATUS_AUTORIZADA, PdvVendaNfce::STATUS_CANCELADA], true)
            ? $emissao
            : null;
        $canceladaEm = $status === PdvVendaNfce::STATUS_CANCELADA ? $emissao : null;

        $xml = $this->normalizeXml($row['XML_TXT'] ?? $row['xml_txt'] ?? $row['XML'] ?? $row['xml'] ?? null);
        $xmlCanc = $this->normalizeXml(
            $row['XML_CANC_TXT'] ?? $row['xml_canc_txt'] ?? $row['XML_CANCELAMENTO'] ?? $row['xml_cancelamento'] ?? null
        );

        return [
            'pdv_venda_id' => (int) $pdvVendaId,
            'empresa_id' => $empresaId,
            'operacao' => PdvFinalizarOperacao::NFCE_TRANSMITIR,
            'modelo' => substr($modelo, 0, 2),
            'serie' => substr($serie, 0, 3),
            'numero' => $numero,
            'cnf' => $cnf !== null ? substr($cnf, 0, 8) : null,
            'chave' => $chave !== null ? substr($chave, 0, 44) : null,
            'protocolo' => $protocolo !== null ? substr($protocolo, 0, 20) : null,
            'protocolo_cancelamento' => null,
            'status' => $status,
            'ambiente' => PdvVendaNfce::AMBIENTE_PRODUCAO,
            'tipo_emissao' => '1',
            'simulada' => false,
            'xml' => $xml,
            'xml_cancelamento' => $xmlCanc,
            'motivo_rejeicao' => $status === PdvVendaNfce::STATUS_REJEITADA ? $motivoCancel : null,
            'motivo_contingencia' => $status === PdvVendaNfce::STATUS_CONTINGENCIA ? $motivoCancel : null,
            'autorizada_em' => $autorizadaEm,
            'cancelada_em' => $canceladaEm,
            '_motivo_cancel' => $motivoCancel,
            '_situacao' => $situacao,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $pdvVendaIdByNumero = PdvVenda::query()
            ->pluck('id', 'numero')
            ->mapWithKeys(fn ($id, $numero) => [(int) $numero => (int) $id])
            ->all();

        $empresaIdByCodigo = Empresa::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $fallbackEmpresaId = Empresa::query()->orderBy('id')->value('id');
        $fallbackEmpresaId = $fallbackEmpresaId !== null ? (int) $fallbackEmpresaId : null;

        DB::transaction(function () use (
            $rows,
            $updateExisting,
            $dryRun,
            $pdvVendaIdByNumero,
            $empresaIdByCodigo,
            $fallbackEmpresaId,
            &$stats,
        ): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow($row, $pdvVendaIdByNumero, $empresaIdByCodigo, $fallbackEmpresaId);

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                unset($payload['_motivo_cancel'], $payload['_situacao']);

                if ($payload['xml'] === null) {
                    unset($payload['xml']);
                }
                if ($payload['xml_cancelamento'] === null) {
                    unset($payload['xml_cancelamento']);
                }

                $existing = PdvVendaNfce::query()
                    ->where('pdv_venda_id', $payload['pdv_venda_id'])
                    ->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                if ($existing) {
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    PdvVendaNfce::query()->create($payload);
                    $stats['created']++;
                }

                PdvVenda::query()->whereKey($payload['pdv_venda_id'])->update([
                    'fiscal' => true,
                    'nfce_operacao' => PdvFinalizarOperacao::NFCE_TRANSMITIR,
                ]);
            }
        });

        return $stats;
    }

    protected function mapStatus(string $situacao): string
    {
        return match ($situacao) {
            'T' => PdvVendaNfce::STATUS_AUTORIZADA,
            'C' => PdvVendaNfce::STATUS_CANCELADA,
            'I' => 'inutilizada',
            'D' => PdvVendaNfce::STATUS_REJEITADA,
            'O' => PdvVendaNfce::STATUS_CONTINGENCIA,
            'P', 'G' => PdvVendaNfce::STATUS_PENDENTE,
            default => PdvVendaNfce::STATUS_AUTORIZADA,
        };
    }

    protected function normalizeXml(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $xml = trim((string) $value);
        if ($xml === '' || strtoupper($xml) === '<NULL>') {
            return null;
        }

        // isql às vezes prefixa o handle do BLOB (ex.: "9e:1") antes do XML.
        if (! str_starts_with($xml, '<?xml') && ! str_starts_with($xml, '<')) {
            $pos = strpos($xml, '<?xml');
            if ($pos === false) {
                $pos = strpos($xml, '<nfeProc');
            }
            if ($pos === false) {
                $pos = strpos($xml, '<NFe');
            }
            if ($pos === false) {
                return null;
            }
            $xml = substr($xml, $pos);
        }

        return $xml !== '' ? $xml : null;
    }

    protected function mapDateTime(mixed $date, mixed $time): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $d = Carbon::parse($date)->format('Y-m-d');
            $t = '00:00:00';
            if ($time !== null && $time !== '') {
                $raw = trim((string) $time);
                if (preg_match('/^(\d{1,2}:\d{2}:\d{2})/', $raw, $m)) {
                    $t = $m[1];
                }
            }

            return $d.' '.$t;
        } catch (\Throwable) {
            return null;
        }
    }
}
