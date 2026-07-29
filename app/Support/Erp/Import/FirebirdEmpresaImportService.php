<?php

namespace App\Support\Erp\Import;

use App\Models\Empresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdEmpresaImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapFirebirdRow(array $row): array
    {
        $fantasia = Str::upper(trim((string) ($row['FANTASIA'] ?? $row['fantasia'] ?? '')));
        $razao = Str::upper(trim((string) ($row['RAZAO'] ?? $row['razao'] ?? '')));
        $cnpj = preg_replace('/\D/', '', (string) ($row['CNPJ'] ?? $row['cnpj'] ?? '')) ?: null;

        return [
            'codigo' => filled($row['CODIGO'] ?? $row['codigo'] ?? null)
                ? (int) ($row['CODIGO'] ?? $row['codigo'])
                : null,
            'nome' => $fantasia !== '' ? $fantasia : ($razao !== '' ? $razao : 'EMPRESA'),
            'fantasia' => $fantasia !== '' ? $fantasia : null,
            'razao_social' => $razao !== '' ? $razao : null,
            'pessoa_tipo' => $this->mapPessoaTipo($row['TIPO'] ?? null, (string) ($cnpj ?? '')),
            'cnpj' => $cnpj,
            'ie' => trim((string) ($row['IE'] ?? '')) ?: null,
            'im' => trim((string) ($row['IM'] ?? '')) ?: null,
            'cnae' => trim((string) ($row['CNAE'] ?? '')) ?: null,
            'regime_tributario' => $this->mapRegime($row['CRT'] ?? null),
            'cep' => preg_replace('/\D/', '', (string) ($row['CEP'] ?? '')) ?: null,
            'endereco' => $this->upperOrNull($row['ENDERECO'] ?? null),
            'numero' => trim((string) ($row['NUMERO'] ?? '')) ?: null,
            'complemento' => $this->upperOrNull($row['COMPLEMENTO'] ?? null),
            'bairro' => $this->upperOrNull($row['BAIRRO'] ?? null),
            'cidade' => $this->upperOrNull($row['CIDADE'] ?? null),
            'cidade_codigo' => trim((string) ($row['ID_CIDADE'] ?? '')) ?: null,
            'uf' => $this->mapUf($row['UF'] ?? null),
            'pais_codigo' => trim((string) ($row['CODIGO_PAIS'] ?? '1058')) ?: '1058',
            'pais' => 'BRASIL',
            'email' => trim((string) ($row['EMAIL'] ?? '')) ?: null,
            'site' => trim((string) ($row['SITE'] ?? '')) ?: null,
            'telefone' => trim((string) ($row['FONE'] ?? '')) ?: null,
            'responsavel' => $this->upperOrNull($row['RESPONSAVEL_EMPRESA'] ?? null),
            'cnpj_representante' => preg_replace('/\D/', '', (string) ($row['CNPJ_REPRESENTANTE'] ?? '')) ?: null,
            'obs_fisco' => trim((string) ($row['OBSFISCO'] ?? '')) ?: null,
            'obs_carne' => trim((string) ($row['OBS_CARNE'] ?? '')) ?: null,
            'obs_nfce' => trim((string) ($row['OBSNFCE'] ?? '')) ?: null,
            'ativo' => true,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int, empresa_id: int|null}
     */
    public function importRows(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'empresa_id' => null];

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow($row);

                if (($payload['nome'] ?? '') === '') {
                    $stats['skipped']++;

                    continue;
                }

                $existing = null;

                if (! empty($payload['cnpj'])) {
                    $existing = Empresa::query()->where('cnpj', $payload['cnpj'])->first();
                }

                if (! $existing && ! empty($payload['codigo'])) {
                    $existing = Empresa::query()->where('codigo', $payload['codigo'])->first();
                }

                if (! $existing) {
                    $existing = Empresa::query()->orderBy('id')->first();
                }

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;
                    $stats['empresa_id'] = (int) $existing->id;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;
                    $stats['empresa_id'] = $existing?->id;

                    continue;
                }

                if ($existing) {
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                    $stats['empresa_id'] = (int) $existing->id;
                } else {
                    $empresa = Empresa::query()->create($payload);
                    $stats['created']++;
                    $stats['empresa_id'] = (int) $empresa->id;
                }
            }
        });

        return $stats;
    }

    protected function mapPessoaTipo(mixed $tipo, string $cnpj): string
    {
        $value = Str::upper(Str::ascii(trim((string) ($tipo ?? ''))));

        if (str_contains($value, 'JUR') || $value === 'J') {
            return Empresa::PESSOA_JURIDICA;
        }

        if (str_contains($value, 'FIS') || $value === 'F') {
            return Empresa::PESSOA_FISICA;
        }

        return strlen($cnpj) > 11 ? Empresa::PESSOA_JURIDICA : Empresa::PESSOA_FISICA;
    }

    protected function mapRegime(mixed $crt): ?string
    {
        return match ((int) $crt) {
            1 => 'simples',
            2 => 'simples',
            3 => 'normal',
            default => null,
        };
    }

    protected function mapUf(mixed $value): ?string
    {
        $uf = Str::upper(trim((string) ($value ?? '')));

        return strlen($uf) === 2 ? $uf : null;
    }

    protected function upperOrNull(mixed $value): ?string
    {
        $text = Str::upper(trim((string) ($value ?? '')));

        return $text !== '' ? $text : null;
    }
}
