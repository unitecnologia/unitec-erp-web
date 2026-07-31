<?php

namespace App\Support\Erp\Import;

use App\Models\Compra;
use App\Models\Empresa;
use App\Models\NotaFornecedor;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirebirdNotaFornecedorImportService
{
    /**
     * Mapeia linha de NFE_MANIFESTO (lista DF-e / Notas de compra do legado).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $pessoaIdByCnpj
     * @param  array<string, int>  $empresaIdByCodigo
     * @param  array<string, int>  $compraIdByChave
     * @return array<string, mixed>|null
     */
    public function mapManifestoRow(
        array $row,
        array $pessoaIdByCnpj,
        array $empresaIdByCodigo,
        array $compraIdByChave,
        ?int $fallbackEmpresaId,
    ): ?array {
        $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);
        if ($codigo < 1) {
            return null;
        }

        $fkEmpresa = trim((string) ($row['FK_EMPRESA'] ?? $row['fk_empresa'] ?? $row['EMPRESA'] ?? $row['empresa'] ?? ''));
        $empresaId = $fkEmpresa !== ''
            ? ($empresaIdByCodigo[$fkEmpresa] ?? $empresaIdByCodigo[(string) (int) $fkEmpresa] ?? $fallbackEmpresaId)
            : $fallbackEmpresaId;

        $dataEmissao = $this->mapDate($row['DT_EMISSAO'] ?? $row['dt_emissao'] ?? null)
            ?? $this->mapDate($row['DT_ENTRADA'] ?? $row['dt_entrada'] ?? null);
        $dataEntrada = $this->mapDate($row['DT_ENTRADA'] ?? $row['dt_entrada'] ?? null) ?? $dataEmissao;

        if ($dataEmissao === null || $dataEntrada === null) {
            return null;
        }

        $chave = preg_replace('/\D/', '', (string) ($row['CHAVE'] ?? $row['chave'] ?? '')) ?: null;
        $numero = trim((string) ($row['NUMERO'] ?? $row['numero'] ?? ''));
        if ($numero === '' || strtoupper($numero) === '<NULL>') {
            $numero = (string) $codigo;
        }

        $cnpjDigits = preg_replace('/\D/', '', (string) ($row['CNPJ'] ?? $row['cnpj'] ?? '')) ?: null;
        $cnpj = $cnpjDigits !== null ? substr($cnpjDigits, 0, 14) : null;

        $nome = trim((string) ($row['NOME'] ?? $row['nome'] ?? ''));
        if ($nome === '' || strtoupper($nome) === '<NULL>') {
            $nome = 'Fornecedor';
            if ($cnpj !== null && isset($pessoaIdByCnpj[$cnpj])) {
                $pessoa = Person::query()->find($pessoaIdByCnpj[$cnpj]);
                if ($pessoa) {
                    $nome = trim((string) ($pessoa->nome_razao ?: $pessoa->apelido_fantasia ?: 'Fornecedor')) ?: 'Fornecedor';
                }
            }
        }

        $nsuRaw = trim((string) ($row['NSU'] ?? $row['nsu'] ?? ''));
        $nsu = ($nsuRaw !== '' && strtoupper($nsuRaw) !== '<NULL>')
            ? substr($nsuRaw, 0, 30)
            : null;

        $compraId = null;
        if ($chave !== null && isset($compraIdByChave[$chave])) {
            $compraId = $compraIdByChave[$chave];
        }

        $xml = $this->normalizeXml($row['XML_TXT'] ?? $row['xml_txt'] ?? $row['XML'] ?? $row['xml'] ?? null);

        $situacao = strtoupper(trim((string) ($row['SITUACAO'] ?? $row['situacao'] ?? '')));
        $gerou = strtoupper(trim((string) ($row['GEROU'] ?? $row['gerou'] ?? '')));

        $status = $this->mapStatus($situacao, $gerou, $compraId !== null);

        return [
            'codigo_legado' => $codigo,
            'empresa_id' => $empresaId ? (int) $empresaId : null,
            'data_entrada' => $dataEntrada,
            'data_emissao' => $dataEmissao,
            'numero' => substr($numero, 0, 20),
            'chave' => $chave !== null ? substr($chave, 0, 44) : null,
            'cnpj' => $cnpj,
            'nome' => Str::limit($nome, 255, ''),
            'nsu' => $nsu,
            'total' => BrDecimalImport::parse($row['VALOR'] ?? $row['valor'] ?? $row['TOTAL'] ?? 0),
            'xml' => $xml,
            'status' => $status,
            'compra_id' => $compraId,
        ];
    }

    /**
     * Compat: XML_MASTER (legado antigo / enriquecimento).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $pessoaIdByCodigo
     * @param  array<string, int>  $empresaIdByCodigo
     * @param  array<string, int>  $compraIdByChave
     * @return array<string, mixed>|null
     */
    public function mapMasterRow(
        array $row,
        array $pessoaIdByCodigo,
        array $empresaIdByCodigo,
        array $compraIdByChave,
        ?int $fallbackEmpresaId,
    ): ?array {
        $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);
        if ($codigo < 1) {
            return null;
        }

        $fkEmpresa = trim((string) ($row['EMPRESA'] ?? $row['empresa'] ?? ''));
        $empresaId = $fkEmpresa !== ''
            ? ($empresaIdByCodigo[$fkEmpresa] ?? $empresaIdByCodigo[(string) (int) $fkEmpresa] ?? $fallbackEmpresaId)
            : $fallbackEmpresaId;

        $dataEmissao = $this->mapDate($row['DATA_EMISSAO_NF'] ?? $row['data_emissao_nf'] ?? null)
            ?? $this->mapDate($row['DATA_ENTRADA'] ?? $row['data_entrada'] ?? null);
        $dataEntrada = $this->mapDate($row['DATA_ENTRADA'] ?? $row['data_entrada'] ?? null) ?? $dataEmissao;

        if ($dataEmissao === null || $dataEntrada === null) {
            return null;
        }

        $chave = preg_replace('/\D/', '', (string) ($row['CHAVE'] ?? $row['chave'] ?? '')) ?: null;
        $numero = trim((string) ($row['NOTA_FISCAL'] ?? $row['nota_fiscal'] ?? ''));
        if ($numero === '' || strtoupper($numero) === '<NULL>') {
            $numero = (string) $codigo;
        }

        $fkFornecedor = trim((string) ($row['ID_FORNECEDOR'] ?? $row['id_fornecedor'] ?? ''));
        $pessoaId = $fkFornecedor !== '' && (int) $fkFornecedor > 0
            ? ($pessoaIdByCodigo[$fkFornecedor] ?? $pessoaIdByCodigo[(string) (int) $fkFornecedor] ?? null)
            : null;

        $nome = 'Fornecedor';
        $cnpj = null;
        if ($pessoaId) {
            $pessoa = Person::query()->find($pessoaId);
            if ($pessoa) {
                $nome = trim((string) ($pessoa->nome_razao ?: $pessoa->apelido_fantasia ?: 'Fornecedor')) ?: 'Fornecedor';
                $digits = preg_replace('/\D/', '', (string) ($pessoa->cpf_cnpj ?? '')) ?: null;
                $cnpj = $digits !== null ? substr($digits, 0, 14) : null;
            }
        } else {
            Log::warning('Migra FB notas_fornecedor: fornecedor não encontrado', [
                'codigo' => $codigo,
                'id_fornecedor' => $fkFornecedor,
            ]);
        }

        $compraId = null;
        if ($chave !== null && isset($compraIdByChave[$chave])) {
            $compraId = $compraIdByChave[$chave];
        }

        $xml = $this->normalizeXml($row['XML_TXT'] ?? $row['xml_txt'] ?? $row['XML'] ?? $row['xml'] ?? null);

        $status = $compraId
            ? NotaFornecedor::STATUS_GEROU_COMPRAS
            : NotaFornecedor::STATUS_ACEITA;

        return [
            'codigo_legado' => $codigo,
            'empresa_id' => $empresaId ? (int) $empresaId : null,
            'data_entrada' => $dataEntrada,
            'data_emissao' => $dataEmissao,
            'numero' => substr($numero, 0, 20),
            'chave' => $chave !== null ? substr($chave, 0, 44) : null,
            'cnpj' => $cnpj,
            'nome' => Str::limit($nome, 255, ''),
            'nsu' => null,
            'total' => BrDecimalImport::parse($row['TOTAL'] ?? 0),
            'xml' => $xml,
            'status' => $status,
            'compra_id' => $compraId,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $manifestoRows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $manifestoRows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $pessoaIdByCnpj = Person::query()
            ->whereNotNull('cpf_cnpj')
            ->get(['id', 'cpf_cnpj'])
            ->mapWithKeys(function (Person $p) {
                $digits = preg_replace('/\D/', '', (string) $p->cpf_cnpj) ?: '';

                return $digits !== '' ? [$digits => (int) $p->id] : [];
            })
            ->all();

        $empresaIdByCodigo = Empresa::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $fallbackEmpresaId = Empresa::query()->orderBy('id')->value('id');
        $fallbackEmpresaId = $fallbackEmpresaId !== null ? (int) $fallbackEmpresaId : null;

        $compraIdByChave = Compra::query()
            ->whereNotNull('chave_nfe')
            ->where('chave_nfe', '!=', '')
            ->pluck('id', 'chave_nfe')
            ->mapWithKeys(fn ($id, $chave) => [(string) $chave => (int) $id])
            ->all();

        DB::transaction(function () use (
            $manifestoRows,
            $pessoaIdByCnpj,
            $empresaIdByCodigo,
            $compraIdByChave,
            $fallbackEmpresaId,
            $updateExisting,
            $dryRun,
            &$stats,
        ): void {
            foreach ($manifestoRows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapManifestoRow(
                    $row,
                    $pessoaIdByCnpj,
                    $empresaIdByCodigo,
                    $compraIdByChave,
                    $fallbackEmpresaId,
                );

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                if ($payload['xml'] === null) {
                    unset($payload['xml']);
                }

                $existing = $this->findExisting($payload);

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                if ($existing) {
                    // Não apaga XML já gravado se o manifesto vier sem BLOB.
                    if (! array_key_exists('xml', $payload) && filled($existing->xml)) {
                        // keep existing xml
                    }
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    NotaFornecedor::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findExisting(array $payload): ?NotaFornecedor
    {
        $existing = null;
        if (! empty($payload['chave'])) {
            $existing = NotaFornecedor::query()->where('chave', $payload['chave'])->first();
        }

        if ($existing) {
            return $existing;
        }

        $byLegado = NotaFornecedor::query()
            ->where('codigo_legado', $payload['codigo_legado'])
            ->first();

        if (! $byLegado) {
            return null;
        }

        $byLegadoChave = preg_replace('/\D/', '', (string) ($byLegado->chave ?? '')) ?: '';
        $payloadChave = (string) ($payload['chave'] ?? '');

        // Evita colidir codigo_legado de XML_MASTER antigo com NFE_MANIFESTO.
        if ($byLegadoChave === '' || $byLegadoChave === $payloadChave) {
            return $byLegado;
        }

        $byLegado->codigo_legado = null;
        $byLegado->save();

        return null;
    }

    protected function mapStatus(string $situacao, string $gerou, bool $temCompra): string
    {
        if ($temCompra || in_array($gerou, ['S', '1', 'T', 'TRUE', 'SIM'], true)) {
            return NotaFornecedor::STATUS_GEROU_COMPRAS;
        }

        return match ($situacao) {
            'C', 'A', 'CONFIRMADA', 'ACEITA', 'CIENCIA', 'CIÊNCIA' => NotaFornecedor::STATUS_ACEITA,
            'D', 'DESCONHECIDA', 'N' => NotaFornecedor::STATUS_DESCONHECIDA,
            default => NotaFornecedor::STATUS_PENDENTE,
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

        // isql às vezes devolve handle de BLOB.
        if (preg_match('/^[0-9a-f]+:[0-9a-f]+$/i', $xml)) {
            return null;
        }

        if (! str_starts_with($xml, '<?xml') && ! str_starts_with($xml, '<')) {
            $pos = strpos($xml, '<?xml');
            if ($pos === false) {
                $pos = strpos($xml, '<nfeProc');
            }
            if ($pos === false) {
                $pos = strpos($xml, '<NFe');
            }
            if ($pos === false) {
                $pos = strpos($xml, '<resNFe');
            }
            if ($pos === false) {
                return null;
            }
            $xml = substr($xml, $pos);
        }

        return $xml !== '' ? $xml : null;
    }

    protected function mapDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || strtoupper($raw) === '<NULL>') {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
