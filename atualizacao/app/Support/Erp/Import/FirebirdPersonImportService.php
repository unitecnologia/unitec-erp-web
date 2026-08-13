<?php

namespace App\Support\Erp\Import;

use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdPersonImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapFirebirdRow(array $row): array
    {
        $codigo = (string) ($row['CODIGO'] ?? $row['codigo'] ?? '');
        $cpfCnpj = trim((string) ($row['CNPJ'] ?? $row['cnpj'] ?? ''));
        $ie = trim((string) ($row['IE'] ?? $row['ie'] ?? ''));

        return [
            'codigo' => $codigo,
            'pessoa_tipo' => $this->mapPessoaTipo($row['TIPO'] ?? $row['tipo'] ?? null, $cpfCnpj),
            'nome_razao' => Str::upper(trim((string) ($row['RAZAO'] ?? $row['razao'] ?? ''))),
            'apelido_fantasia' => $this->nullableUpper($row['FANTASIA'] ?? $row['fantasia'] ?? null),
            'cpf_cnpj' => $cpfCnpj !== '' ? $cpfCnpj : null,
            'rg_ie' => $ie !== '' ? $ie : null,
            'cep' => trim((string) ($row['CEP'] ?? $row['cep'] ?? '')) ?: null,
            'endereco' => $this->nullableUpper($row['ENDERECO'] ?? $row['endereco'] ?? null),
            'numero' => trim((string) ($row['NUMERO'] ?? $row['numero'] ?? '')) ?: null,
            'complemento' => $this->nullableUpper($row['COMPLEMENTO'] ?? $row['complemento'] ?? null),
            'bairro' => $this->nullableUpper($row['BAIRRO'] ?? $row['bairro'] ?? null),
            'cidade_codigo' => filled($row['CODMUN'] ?? $row['codmun'] ?? null)
                ? (string) ($row['CODMUN'] ?? $row['codmun'])
                : null,
            'cidade_nome' => $this->nullableUpper($row['MUNICIPIO'] ?? $row['municipio'] ?? null),
            'uf' => $this->mapUf($row['UF'] ?? $row['uf'] ?? null),
            'email' => trim((string) ($row['EMAIL1'] ?? $row['email1'] ?? '')) ?: null,
            'email2' => trim((string) ($row['EMAIL2'] ?? $row['email2'] ?? '')) ?: null,
            'fone1' => trim((string) ($row['FONE1'] ?? $row['fone1'] ?? '')) ?: null,
            'fone2' => trim((string) ($row['FONE2'] ?? $row['fone2'] ?? '')) ?: null,
            'celular1' => trim((string) ($row['CELULAR1'] ?? $row['celular1'] ?? '')) ?: null,
            'celular2' => trim((string) ($row['CELULAR2'] ?? $row['celular2'] ?? '')) ?: null,
            'whatsapp' => trim((string) ($row['WHATSAPP'] ?? $row['whatsapp'] ?? '')) ?: null,
            'regime_tributario' => $this->mapRegimeTributario($row['REGIME_TRIBUTARIO'] ?? $row['regime_tributario'] ?? null),
            'tipo_recebimento' => $this->mapTipoRecebimento($row['TIPO_RECEBIMENTO'] ?? $row['tipo_recebimento'] ?? null),
            'tipo_contribuinte' => $this->mapTipoContribuinte($row, $ie),
            'nome_mae' => $this->nullableUpper($row['MAE'] ?? $row['mae'] ?? null),
            'nome_pai' => $this->nullableUpper($row['PAI'] ?? $row['pai'] ?? null),
            'data_nascimento' => $this->mapDate($row['DT_NASC'] ?? $row['dt_nasc'] ?? null),
            'limite_credito' => BrDecimalImport::parse($row['LIMITE'] ?? 0),
            'dia_pgto' => filled($row['DIA_PGTO'] ?? $row['dia_pgto'] ?? null)
                ? (int) ($row['DIA_PGTO'] ?? $row['dia_pgto'])
                : null,
            'estado_civil' => $this->mapEstadoCivil($row['ECIVIL'] ?? $row['ecivil'] ?? null),
            'sexo' => $this->mapSexo($row['SEXO'] ?? $row['sexo'] ?? null),
            'salario' => BrDecimalImport::parse($row['SALARIO'] ?? 0),
            'data_admissao' => $this->mapDate($row['DT_ADMISSAO'] ?? $row['dt_admissao'] ?? null),
            'data_demissao' => $this->mapDate($row['DT_DEMISSAO'] ?? $row['dt_demissao'] ?? null),
            'observacoes' => $this->mapObservacoes($row['OBS'] ?? $row['obs'] ?? null),
            'banco' => $this->nullableUpper($row['BANCO'] ?? $row['banco'] ?? null),
            'agencia' => trim((string) ($row['AGENCIA'] ?? $row['agencia'] ?? '')) ?: null,
            'gerente' => $this->nullableUpper($row['GERENTE'] ?? $row['gerente'] ?? null),
            'fone_gerente' => trim((string) ($row['FONE_GERENTE'] ?? $row['fone_gerente'] ?? '')) ?: null,
            'is_atendente' => $this->snToBool($row['ATENDENTE'] ?? 'N'),
            'is_tecnico' => $this->snToBool($row['TECNICO'] ?? 'N'),
            'is_cliente' => $this->snToBool($row['CLI'] ?? 'N'),
            'is_fornecedor' => $this->snToBool($row['FORN'] ?? 'N'),
            'is_funcionario' => $this->snToBool($row['FUN'] ?? 'N'),
            'is_administradora' => $this->snToBool($row['ADM'] ?? 'N'),
            'is_parceiro' => $this->snToBool($row['PARC'] ?? 'N'),
            'is_fabricante' => $this->snToBool($row['FAB'] ?? 'N'),
            'is_transportadora' => $this->snToBool($row['TRAN'] ?? 'N'),
            'is_ccf_spc' => $this->snToBool($row['CCF'] ?? 'N') || $this->snToBool($row['SPC'] ?? 'N'),
            'ativo' => $this->snToBool($row['ATIVO'] ?? 'S'),
            '_dt_cadastro' => $this->mapDate($row['DT_CADASTRO'] ?? $row['dt_cadastro'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $rows, bool $updateExisting = false, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow($row);
                $dtCadastro = $payload['_dt_cadastro'] ?? null;
                unset($payload['_dt_cadastro']);

                if ($payload['codigo'] === '' || $payload['nome_razao'] === '') {
                    $stats['skipped']++;

                    continue;
                }

                $existing = Person::query()->where('codigo', $payload['codigo'])->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                if ($existing) {
                    $existing->update($payload);
                    $stats['updated']++;
                } else {
                    $person = Person::query()->create($payload);

                    if ($dtCadastro !== null) {
                        $person->forceFill(['created_at' => Carbon::parse($dtCadastro)->startOfDay()])->saveQuietly();
                    }

                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    protected function mapPessoaTipo(mixed $tipo, string $cpfCnpj): string
    {
        $value = Str::upper(trim((string) ($tipo ?? '')));

        if (in_array($value, ['J', 'JURIDICA', 'JURÍDICA', 'PJ'], true)) {
            return Person::PESSOA_JURIDICA;
        }

        if (in_array($value, ['F', 'FISICA', 'FÍSICA', 'PF'], true)) {
            return Person::PESSOA_FISICA;
        }

        $digits = preg_replace('/\D/', '', $cpfCnpj) ?? '';

        return strlen($digits) > 11 ? Person::PESSOA_JURIDICA : Person::PESSOA_FISICA;
    }

    protected function mapRegimeTributario(mixed $value): ?string
    {
        $normalized = Str::upper(Str::ascii(trim((string) ($value ?? ''))));

        return match (true) {
            str_contains($normalized, 'SIMPLE') => 'simples',
            str_contains($normalized, 'PRESUM') => 'presumido',
            str_contains($normalized, 'REAL') => 'real',
            default => null,
        };
    }

    protected function mapTipoRecebimento(mixed $value): ?string
    {
        $normalized = Str::upper(Str::ascii(trim((string) ($value ?? ''))));

        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'DINHEIRO') => 'dinheiro',
            str_contains($normalized, 'CART') => 'cartao',
            str_contains($normalized, 'BOLETO') => 'boleto',
            str_contains($normalized, 'PIX') => 'pix',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function mapTipoContribuinte(array $row, string $ie): string
    {
        if ($this->snToBool($row['ISENTO'] ?? 'N')) {
            return 'isento';
        }

        if ($ie !== '') {
            return 'contribuinte';
        }

        return 'nao_contribuinte';
    }

    protected function mapEstadoCivil(mixed $value): ?string
    {
        $normalized = Str::upper(Str::ascii(trim((string) ($value ?? ''))));

        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'SOLTEIRO') => 'solteiro',
            str_contains($normalized, 'CASADO') => 'casado',
            str_contains($normalized, 'DIVORCI') => 'divorciado',
            str_contains($normalized, 'VIUVO') || str_contains($normalized, 'VIÚVO') => 'viuvo',
            str_contains($normalized, 'UNIAO') || str_contains($normalized, 'ESTAVEL') => 'uniao_estavel',
            default => null,
        };
    }

    protected function mapSexo(mixed $value): ?string
    {
        $normalized = Str::upper(Str::ascii(trim((string) ($value ?? ''))));

        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_starts_with($normalized, 'M') => 'masculino',
            str_starts_with($normalized, 'F') => 'feminino',
            default => 'outro',
        };
    }

    protected function mapUf(mixed $value): ?string
    {
        $uf = Str::upper(trim((string) ($value ?? '')));

        return strlen($uf) === 2 ? $uf : null;
    }

    protected function mapObservacoes(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    protected function nullableUpper(mixed $value): ?string
    {
        $text = Str::upper(trim((string) ($value ?? '')));

        return $text !== '' ? $text : null;
    }

    protected function snToBool(mixed $value): bool
    {
        return in_array(strtoupper(trim((string) $value)), ['S', '1', 'T', 'Y', 'TRUE'], true);
    }

    protected function mapDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
