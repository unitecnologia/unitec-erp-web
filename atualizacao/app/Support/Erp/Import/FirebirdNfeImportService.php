<?php

namespace App\Support\Erp\Import;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeItem;
use App\Models\Person;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdNfeImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $pessoaIdByCodigo
     * @param  array<string, int>  $empresaIdByCodigo
     * @return array<string, mixed>|null
     */
    public function mapMasterRow(
        array $row,
        array $pessoaIdByCodigo,
        array $empresaIdByCodigo,
        ?int $fallbackEmpresaId,
    ): ?array {
        $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);
        if ($codigo < 1) {
            return null;
        }

        $numero = (int) ($row['NUMERO'] ?? $row['numero'] ?? 0);
        if ($numero < 1) {
            return null;
        }

        $fkEmpresa = trim((string) ($row['FKEMPRESA'] ?? $row['fkempresa'] ?? $row['ID_EMITENTE'] ?? $row['id_emitente'] ?? ''));
        $empresaId = $fkEmpresa !== ''
            ? ($empresaIdByCodigo[$fkEmpresa] ?? $empresaIdByCodigo[(string) (int) $fkEmpresa] ?? $fallbackEmpresaId)
            : $fallbackEmpresaId;

        if (! $empresaId) {
            return null;
        }

        $fkCliente = trim((string) ($row['ID_CLIENTE'] ?? $row['id_cliente'] ?? ''));
        $clienteId = $fkCliente !== '' && (int) $fkCliente > 0
            ? ($pessoaIdByCodigo[$fkCliente] ?? $pessoaIdByCodigo[(string) (int) $fkCliente] ?? null)
            : null;

        $fkTransp = trim((string) ($row['ID_TRANSPORTADOR'] ?? $row['id_transportador'] ?? ''));
        $transportadoraId = $fkTransp !== '' && (int) $fkTransp > 0
            ? ($pessoaIdByCodigo[$fkTransp] ?? $pessoaIdByCodigo[(string) (int) $fkTransp] ?? null)
            : null;

        $situacao = trim((string) ($row['SITUACAO'] ?? $row['situacao'] ?? Nfe::SITUACAO_ABERTA));
        if ($situacao === '') {
            $situacao = Nfe::SITUACAO_ABERTA;
        }

        $status = $this->mapStatus($situacao);
        $chave = preg_replace('/\D/', '', (string) ($row['CHAVE'] ?? $row['chave'] ?? '')) ?: null;
        $serie = trim((string) ($row['SERIE'] ?? $row['serie'] ?? '1')) ?: '1';
        $modelo = trim((string) ($row['MODELO'] ?? $row['modelo'] ?? '55')) ?: '55';

        $xml = $this->normalizeXml($row['XML_TXT'] ?? $row['xml_txt'] ?? $row['XML'] ?? $row['xml'] ?? null);
        $xmlCanc = $this->normalizeXml(
            $row['XML_CANC_TXT'] ?? $row['xml_canc_txt'] ?? $row['XML_CANCELAMENTO'] ?? $row['xml_cancelamento'] ?? null
        );

        $dataEmissao = $this->mapDate($row['DATA_EMISSAO'] ?? $row['data_emissao'] ?? null);
        $horaEmissao = $this->mapTime($row['HORA_EMISSAO'] ?? $row['hora_emissao'] ?? null);
        $dataSaida = $this->mapDate($row['DATA_SAIDA'] ?? $row['data_saida'] ?? null) ?? $dataEmissao;
        $horaSaida = $this->mapTime($row['HORA_SAIDA'] ?? $row['hora_saida'] ?? null) ?? $horaEmissao;

        return [
            '_fb_codigo' => $codigo,
            'empresa_id' => (int) $empresaId,
            'numero' => $numero,
            'serie' => substr($serie, 0, 3),
            'modelo' => substr($modelo, 0, 2),
            'data_emissao' => $dataEmissao,
            'hora_emissao' => $horaEmissao,
            'data_saida' => $dataSaida,
            'hora_saida' => $horaSaida,
            'cliente_id' => $clienteId,
            'transportadora_id' => $transportadoraId,
            'chave' => $chave !== null ? substr($chave, 0, 44) : null,
            'protocolo' => $this->nullableString($row['PROTOCOLO'] ?? $row['protocolo'] ?? null, 20),
            'cnf' => $this->nullableString($row['CNF'] ?? $row['cnf'] ?? null, 8),
            'xml' => $xml,
            'xml_cancelamento' => $xmlCanc,
            'obs_fisco' => $this->nullableString($row['OBSFISCO'] ?? $row['obsfisco'] ?? null, 5000),
            'obs_contribuinte' => $this->nullableString($row['OBSCONTRIBUINTE'] ?? $row['obscontribuinte'] ?? null, 5000),
            'total' => BrDecimalImport::parse($row['TOTAL'] ?? 0),
            'subtotal' => BrDecimalImport::parse($row['SUBTOTAL'] ?? 0),
            'desconto' => BrDecimalImport::parse($row['DESCONTO'] ?? 0),
            'frete' => BrDecimalImport::parse($row['FRETE'] ?? 0),
            'seguro' => BrDecimalImport::parse($row['SEGURO'] ?? 0),
            'despesas' => BrDecimalImport::parse($row['DESPESAS'] ?? 0),
            'troco' => BrDecimalImport::parse($row['TROCO'] ?? 0),
            'base_icms' => BrDecimalImport::parse($row['BASEICMS'] ?? $row['baseicms'] ?? 0),
            'total_icms' => BrDecimalImport::parse($row['TOTALICMS'] ?? $row['totalicms'] ?? 0),
            'base_icms_st' => BrDecimalImport::parse($row['BASE_ST'] ?? $row['base_st'] ?? 0),
            'valor_icms_st' => BrDecimalImport::parse($row['TOTAL_ST'] ?? $row['total_st'] ?? 0),
            'base_ipi' => BrDecimalImport::parse($row['BASE_IPI'] ?? $row['base_ipi'] ?? 0),
            'total_ipi' => BrDecimalImport::parse($row['TOTAL_IPI'] ?? $row['total_ipi'] ?? 0),
            'base_icms_pis' => BrDecimalImport::parse($row['BASEICMSPIS'] ?? 0),
            'total_icms_pis' => BrDecimalImport::parse($row['TOTALICMSPIS'] ?? 0),
            'base_icms_cofins' => BrDecimalImport::parse($row['BASEICMSCOF'] ?? 0),
            'total_icms_cofins' => BrDecimalImport::parse($row['TOTALICMSCOFINS'] ?? 0),
            'status' => $status,
            'situacao' => substr($situacao, 0, 1),
            'finalidade' => $this->nullableString($row['FINALIDADE'] ?? $row['finalidade'] ?? '1', 1) ?: '1',
            'movimento' => $this->nullableString($row['MOVIMENTO'] ?? $row['movimento'] ?? '1', 1) ?: '1',
            'consumidor_final' => $this->nullableString($row['CONSUMIDOR_FINAL'] ?? $row['consumidor_final'] ?? '1', 1) ?: '1',
            'tipo_emissao' => $this->nullableString($row['TIPO_EMISSAO'] ?? $row['tipo_emissao'] ?? '1', 1) ?: '1',
            'cfop' => $this->nullableString($row['CFOP'] ?? $row['cfop'] ?? null, 4),
            'npedido' => $this->nullableString($row['NPEDIDO'] ?? $row['npedido'] ?? null, 20),
            'tipo_frete' => $this->nullableString($row['TIPO_FRETE'] ?? $row['tipo_frete'] ?? null, 1),
            'especie' => $this->nullableString($row['ESPECIE'] ?? $row['especie'] ?? null, 60),
            'marca' => $this->nullableString($row['MARCA'] ?? $row['marca'] ?? null, 60),
            'nvol' => $this->nullableString($row['NVOL'] ?? $row['nvol'] ?? null, 60),
            'qvol' => filled($row['QVOL'] ?? null) ? (int) $row['QVOL'] : null,
            'placa' => $this->nullableString($row['PLACA'] ?? $row['placa'] ?? null, 10),
            'rntc' => $this->nullableString($row['RNTC'] ?? $row['rntc'] ?? null, 20),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $productIdByCodigo
     * @return array<string, mixed>|null
     */
    public function mapItemRow(array $row, array $productIdByCodigo): ?array
    {
        $fkNfe = (int) ($row['FKNFE'] ?? $row['fknfe'] ?? 0);
        if ($fkNfe < 1) {
            return null;
        }

        $item = (int) ($row['ITEM'] ?? $row['item'] ?? 0);
        if ($item < 1) {
            return null;
        }

        $fkProduto = trim((string) ($row['ID_PRODUTO'] ?? $row['id_produto'] ?? ''));
        $productId = $fkProduto !== '' && (int) $fkProduto > 0
            ? ($productIdByCodigo[$fkProduto] ?? $productIdByCodigo[(string) (int) $fkProduto] ?? null)
            : null;

        $descricao = trim((string) ($row['DESCRICAO'] ?? $row['descricao'] ?? ''));
        if ($descricao === '' && $productId) {
            $descricao = (string) (Product::query()->whereKey($productId)->value('descricao') ?? '');
        }
        if ($descricao === '') {
            $descricao = 'ITEM '.$item;
        }

        return [
            '_fb_nfe' => $fkNfe,
            'item' => $item,
            'product_id' => $productId,
            'cod_barra' => $this->nullableString($row['COD_BARRA'] ?? $row['cod_barra'] ?? null, 50),
            'ncm' => $this->nullableString($row['NCM'] ?? $row['ncm'] ?? null, 10),
            'cfop' => $this->nullableString($row['CFOP'] ?? $row['cfop'] ?? null, 4),
            'cst' => $this->nullableString($row['CST'] ?? $row['cst'] ?? null, 3),
            'csosn' => $this->nullableString($row['CSOSN'] ?? $row['csosn'] ?? null, 3),
            'cest' => $this->nullableString($row['CEST'] ?? $row['cest'] ?? null, 10),
            'unidade' => $this->nullableString($row['UNIDADE'] ?? $row['unidade'] ?? 'UN', 6) ?: 'UN',
            'descricao' => Str::limit($descricao, 120, ''),
            'descricao_complementar' => $this->nullableString($row['DESCRICAO_COMPLEMENTAR'] ?? null, 500),
            'info_adicionais' => $this->nullableString($row['INFO_ADICIONAIS'] ?? null, 500),
            'quantidade' => BrDecimalImport::parse($row['QTD'] ?? $row['qtd'] ?? 0),
            'valor_unitario' => BrDecimalImport::parse($row['PRECO'] ?? $row['preco'] ?? 0),
            'total' => BrDecimalImport::parse($row['TOTAL'] ?? $row['total'] ?? 0),
            'desconto' => BrDecimalImport::parse($row['DESCONTO'] ?? 0),
            'frete' => BrDecimalImport::parse($row['FRETE'] ?? 0),
            'seguro' => BrDecimalImport::parse($row['SEGURO'] ?? 0),
            'despesas' => BrDecimalImport::parse($row['DESPESAS'] ?? 0),
            'outros' => BrDecimalImport::parse($row['OUTROS'] ?? 0),
            'base_icms' => BrDecimalImport::parse($row['BASE_ICMS'] ?? 0),
            'aliq_icms' => BrDecimalImport::parse($row['ALIQ_ICMS'] ?? 0),
            'valor_icms' => BrDecimalImport::parse($row['VALOR_ICMS'] ?? 0),
            'base_icms_st' => BrDecimalImport::parse($row['BASE_ICMS_ST'] ?? 0),
            'aliq_icms_st' => BrDecimalImport::parse($row['ALIQ_ICMS_ST'] ?? 0),
            'valor_icms_st' => BrDecimalImport::parse($row['VALOR_ICMS_ST'] ?? 0),
            'cst_ipi' => $this->nullableString($row['CST_IPI'] ?? null, 3),
            'base_ipi' => BrDecimalImport::parse($row['BASE_IPI'] ?? 0),
            'aliq_ipi' => BrDecimalImport::parse($row['ALIQ_IPI'] ?? 0),
            'valor_ipi' => BrDecimalImport::parse($row['VALOR_IPI'] ?? 0),
            'cst_pis' => $this->nullableString($row['CST_PIS'] ?? null, 3),
            'base_pis_icms' => BrDecimalImport::parse($row['BASE_PIS_ICMS'] ?? 0),
            'aliq_pis_icms' => BrDecimalImport::parse($row['ALIQ_PIS_ICMS'] ?? 0),
            'valor_pis_icms' => BrDecimalImport::parse($row['VALOR_PIS_ICMS'] ?? 0),
            'cst_cofins' => $this->nullableString($row['CST_COFINS'] ?? null, 3),
            'base_cofins_icms' => BrDecimalImport::parse($row['BASE_COFINS_ICMS'] ?? 0),
            'aliq_cofins_icms' => BrDecimalImport::parse($row['ALIQ_COFINS_ICMS'] ?? 0),
            'valor_cofins_icms' => BrDecimalImport::parse($row['VALOR_COFINS_ICMS'] ?? 0),
            'trib_mun' => BrDecimalImport::parse($row['TRIB_MUN'] ?? 0),
            'trib_est' => BrDecimalImport::parse($row['TRIB_EST'] ?? 0),
            'trib_fed' => BrDecimalImport::parse($row['TRIB_FED'] ?? 0),
            'trib_imp' => BrDecimalImport::parse($row['TRIB_IMP'] ?? 0),
            'vbcufdest' => BrDecimalImport::parse($row['VBCUFDEST'] ?? 0),
            'vicmsufdest' => BrDecimalImport::parse($row['VICMSUFDEST'] ?? 0),
            'vicmsufremet' => BrDecimalImport::parse($row['VICMSUFREMET'] ?? 0),
            'vfcp' => BrDecimalImport::parse($row['VFCP'] ?? 0),
            'motivo_desoneracao' => $this->nullableString($row['MOTDESICMS'] ?? null, 2),
            'base_desoneracao' => BrDecimalImport::parse($row['BASE_DESONERACAO'] ?? 0),
            'valor_desoneracao' => BrDecimalImport::parse($row['VICMSDESON'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $masterRows
     * @param  list<array<string, mixed>>  $itemRows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(array $masterRows, array $itemRows = [], bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $pessoaIdByCodigo = Person::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $empresaIdByCodigo = Empresa::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $productIdByCodigo = Product::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();

        $fallbackEmpresaId = Empresa::query()->orderBy('id')->value('id');
        $fallbackEmpresaId = $fallbackEmpresaId !== null ? (int) $fallbackEmpresaId : null;

        /** @var array<int, list<array<string, mixed>>> $itemsByFb */
        $itemsByFb = [];
        foreach ($itemRows as $itemRow) {
            if (! is_array($itemRow)) {
                continue;
            }
            $mapped = $this->mapItemRow($itemRow, $productIdByCodigo);
            if ($mapped === null) {
                continue;
            }
            $fb = (int) $mapped['_fb_nfe'];
            unset($mapped['_fb_nfe']);
            $itemsByFb[$fb][] = $mapped;
        }

        DB::transaction(function () use (
            $masterRows,
            $itemsByFb,
            $pessoaIdByCodigo,
            $empresaIdByCodigo,
            $fallbackEmpresaId,
            $updateExisting,
            $dryRun,
            &$stats,
        ): void {
            foreach ($masterRows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapMasterRow($row, $pessoaIdByCodigo, $empresaIdByCodigo, $fallbackEmpresaId);
                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $fbCodigo = (int) $payload['_fb_codigo'];
                unset($payload['_fb_codigo']);

                if ($payload['xml'] === null) {
                    unset($payload['xml']);
                }
                if ($payload['xml_cancelamento'] === null) {
                    unset($payload['xml_cancelamento']);
                }

                $existing = null;
                if (! empty($payload['chave'])) {
                    $existing = Nfe::query()->where('chave', $payload['chave'])->first();
                }
                if (! $existing) {
                    $existing = Nfe::query()
                        ->where('empresa_id', $payload['empresa_id'])
                        ->where('serie', $payload['serie'])
                        ->where('numero', $payload['numero'])
                        ->where('modelo', $payload['modelo'])
                        ->first();
                }

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                $itens = $itemsByFb[$fbCodigo] ?? [];
                $payload['total_itens'] = count($itens);

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                if ($existing) {
                    $existing->fill($payload)->save();
                    $nfe = $existing;
                    $stats['updated']++;
                } else {
                    $nfe = Nfe::query()->create($payload);
                    $stats['created']++;
                }

                NfeItem::query()->where('nfe_id', $nfe->id)->delete();
                foreach ($itens as $itemPayload) {
                    $itemPayload['nfe_id'] = $nfe->id;
                    NfeItem::query()->create($itemPayload);
                }
            }
        });

        return $stats;
    }

    protected function mapStatus(string $situacao): string
    {
        return match ($situacao) {
            Nfe::SITUACAO_ABERTA => Nfe::STATUS_ABERTA,
            Nfe::SITUACAO_TRANSMITIDA => Nfe::STATUS_TRANSMITIDA,
            Nfe::SITUACAO_CANCELADA => Nfe::STATUS_CANCELADA,
            Nfe::SITUACAO_DUPLICIDADE => Nfe::STATUS_DUPLICIDADE,
            Nfe::SITUACAO_INUTILIZADA => Nfe::STATUS_INUTILIZADA,
            Nfe::SITUACAO_DENEGADA => Nfe::STATUS_DENEGADA,
            Nfe::SITUACAO_CONTINGENCIA => Nfe::STATUS_CONTINGENCIA,
            default => Nfe::STATUS_TRANSMITIDA,
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

    protected function mapTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if (preg_match('/^(\d{1,2}:\d{2}:\d{2})/', $raw, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function nullableString(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '' || strtoupper($text) === '<NULL>') {
            return null;
        }

        // isql às vezes devolve handle de BLOB (ex.: "a6:1f00a") no lugar do texto.
        if (preg_match('/^[0-9a-f]+:[0-9a-f]+$/i', $text)) {
            return null;
        }

        return Str::limit($text, $max, '');
    }
}
