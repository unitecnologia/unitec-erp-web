<?php

namespace App\Support\Erp\Import;

use App\Models\Grupo;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdProductImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapFirebirdRow(array $row): array
    {
        $codigo = (string) ($row['CODIGO'] ?? $row['codigo'] ?? '');

        return [
            'codigo' => $codigo,
            'descricao' => Str::upper(trim((string) ($row['DESCRICAO'] ?? $row['descricao'] ?? ''))),
            'referencia' => trim((string) ($row['REFERENCIA'] ?? $row['referencia'] ?? '')) ?: $codigo,
            'codigo_barras' => $this->normalizeBarcode($row['CODBARRA'] ?? $row['codbarra'] ?? null),
            'codigo_barras_caixa' => trim((string) ($row['COD_BARRA_ATACADO'] ?? $row['cod_barra_atacado'] ?? '')) ?: null,
            'tipo_produto' => $this->mapTipoProduto($row['TIPO'] ?? $row['tipo'] ?? '00'),
            'grupo' => $this->mapGrupo($row),
            'marca' => trim((string) ($row['MARCA_NOME'] ?? $row['marca'] ?? '')) ?: null,
            'unidade' => Str::upper(trim((string) ($row['UNIDADE'] ?? $row['unidade'] ?? 'UN'))),
            'localizacao' => trim((string) ($row['LOCALIZACAO'] ?? $row['localizacao'] ?? '')) ?: null,
            'preco_compra' => BrDecimalImport::parse($row['PR_CUSTO'] ?? 0),
            'pct_custos' => BrDecimalImport::parse($row['PERC_CUSTO'] ?? 0),
            'preco_custo' => BrDecimalImport::parse($row['PR_CUSTO2'] ?? $row['PR_CUSTO'] ?? 0),
            'pct_lucro' => BrDecimalImport::parse($row['MARGEM'] ?? 0),
            'preco_venda' => BrDecimalImport::parse($row['PR_VENDA'] ?? 0),
            'preco_venda_prazo' => BrDecimalImport::parse($row['PR_VENDA_PRAZO'] ?? 0),
            'preco_atacado' => BrDecimalImport::parse($row['PRECO_ATACADO'] ?? 0),
            'qtd_atacado' => BrDecimalImport::parse($row['QTD_ATACADO'] ?? 0, 3),
            'comissao_pct' => BrDecimalImport::parse($row['COMISSAO'] ?? 0),
            'desconto_pct' => BrDecimalImport::parse($row['DESCONTO'] ?? 0),
            'estoque' => BrDecimalImport::parse($row['QTD_ATUAL'] ?? 0, 3),
            'estoque_minimo' => BrDecimalImport::parse($row['QTD_MIN'] ?? 0, 3),
            'estoque_inicial' => BrDecimalImport::parse($row['ESTOQUE_INICIAL'] ?? 0, 3),
            'e_medio' => BrDecimalImport::parse($row['E_MEDIO'] ?? 0, 3),
            'ult_compra' => BrDecimalImport::parse($row['ULT_COMPRA'] ?? 0),
            'ult_compra_anterior' => BrDecimalImport::parse($row['ULT_COMPRA_ANTERIOR'] ?? 0),
            'preco_custo_anterior' => BrDecimalImport::parse($row['PR_CUSTO_ANTERIOR'] ?? 0),
            'preco_venda_anterior' => BrDecimalImport::parse($row['PR_VENDA_ANTERIOR'] ?? 0),
            'peso_kg' => BrDecimalImport::parse($row['PESO'] ?? 0, 3),
            'ncm' => str_pad(preg_replace('/\D/', '', (string) ($row['NCM'] ?? '00000000')), 8, '0', STR_PAD_LEFT),
            'cest' => trim((string) ($row['CEST'] ?? '')) ?: null,
            'cfop_interno' => trim((string) ($row['CFOP'] ?? '5102')),
            'cfop_externo' => trim((string) ($row['CFOP_EXTERNO'] ?? '6102')),
            'origem' => (int) ($row['ORIGEM'] ?? 0),
            'cst_icms' => str_pad(trim((string) ($row['CSTICMS'] ?? '041')), 3, '0', STR_PAD_LEFT),
            'csosn' => str_pad(trim((string) ($row['CSOSN'] ?? '102')), 3, '0', STR_PAD_LEFT),
            'cst_externo' => str_pad(trim((string) ($row['CST_EXTERNO'] ?? '041')), 3, '0', STR_PAD_LEFT),
            'csosn_externo' => str_pad(trim((string) ($row['CSOSN_EXTERNO'] ?? '102')), 3, '0', STR_PAD_LEFT),
            'aliq_icms' => BrDecimalImport::parse($row['ALIQ_ICM'] ?? 0),
            'aliq_icms_externo' => BrDecimalImport::parse($row['ALIQ_ICMS_EXTERNO'] ?? 0),
            'cst_entrada' => str_pad(trim((string) ($row['CSTE'] ?? '07')), 2, '0', STR_PAD_LEFT),
            'cst_saida' => str_pad(trim((string) ($row['CSTS'] ?? '07')), 2, '0', STR_PAD_LEFT),
            'cst_ipi' => str_pad(trim((string) ($row['CSTIPI'] ?? '53')), 2, '0', STR_PAD_LEFT),
            'cod_enq_ipi' => trim((string) ($row['COD_ENQ_IPI'] ?? '')) ?: null,
            'aliq_pis' => BrDecimalImport::parse($row['ALIQ_PIS'] ?? 0),
            'aliq_cofins' => BrDecimalImport::parse($row['ALIQ_COF'] ?? 0),
            'aliq_ipi' => BrDecimalImport::parse($row['ALIQ_IPI'] ?? 0),
            'fcp_pct' => BrDecimalImport::parse($row['FCP'] ?? 0),
            'mva_pct' => BrDecimalImport::parse($row['MVA'] ?? 0),
            'mva_normal' => BrDecimalImport::parse($row['MVA_NORMAL'] ?? 0, 4),
            'reducao_base_pct' => BrDecimalImport::parse($row['REDUCAO_BASE'] ?? 0),
            'icms_diferido' => BrDecimalImport::parse($row['ICMS_DIFERIDO'] ?? 0, 4),
            'aliq_deson' => BrDecimalImport::parse($row['ALIQ_DESON'] ?? 0, 4),
            'motivo_desoneracao' => filled($row['MOTIVO_DESONERACAO'] ?? null) ? (int) $row['MOTIVO_DESONERACAO'] : null,
            'tipo_tributacao' => trim((string) ($row['TIPO_TRIBUTACAO'] ?? '')) ?: null,
            'cod_beneficio' => trim((string) ($row['COD_BENEFICIO'] ?? '')) ?: null,
            'glp_pct' => BrDecimalImport::parse($row['GLP'] ?? 0),
            'gnn_pct' => BrDecimalImport::parse($row['GNN'] ?? 0),
            'gni_pct' => BrDecimalImport::parse($row['GNI'] ?? 0),
            'peso_liq' => BrDecimalImport::parse($row['PESO_LIQ'] ?? 0, 3),
            'anp_code' => trim((string) ($row['ANP'] ?? '')) ?: null,
            'issqn' => BrDecimalImport::parse($row['ISSQN'] ?? 0),
            'prefixo_balanca' => trim((string) ($row['PREFIXO_BALANCA'] ?? '')) ?: null,
            'ativo' => $this->snToBool($row['ATIVO'] ?? 'S'),
            'is_fiscal' => $this->snToBool($row['EFISCAL'] ?? 'S'),
            'paga_comissao' => $this->snToBool($row['PAGA_COMISSAO'] ?? 'N'),
            'preco_variavel' => $this->snToBool($row['PRECO_VARIAVEL'] ?? 'N'),
            'is_composicao' => $this->snToBool($row['COMPOSICAO'] ?? 'N'),
            'is_servico' => $this->snToBool($row['SERVICO'] ?? 'N'),
            'is_grade' => $this->snToBool($row['GRADE'] ?? 'N'),
            'contr_est_grade' => $this->snToBool($row['CONTROLA_ESTOQUE_GRADE'] ?? 'N'),
            'usa_tab_preco' => $this->snToBool($row['USA_TAB_PRECO'] ?? 'N'),
            'is_combustivel' => $this->snToBool($row['COMBUSTIVEL'] ?? 'N'),
            'usa_imei' => $this->snToBool($row['USA_IMEI'] ?? 'N'),
            'produto_pesado' => $this->snToBool($row['PRODUTO_PESADO'] ?? 'N'),
            'tributacao_monofasica' => $this->snToBool($row['TRIBUTACAO_MONOFASICA'] ?? 'N'),
            'is_restaurante' => $this->snToBool($row['RESTAUTANTE'] ?? 'N'),
            'tipo_restaurante' => trim((string) ($row['TIPO_RESTAURANTE'] ?? '')) ?: null,
            'menu_id' => filled($row['ID_MENU'] ?? null) ? (int) $row['ID_MENU'] : null,
            'tipo_alimento' => trim((string) ($row['TIPO_ALIMENTO'] ?? '')) ?: null,
            'qtd_sabores' => (int) ($row['QTD_SABORES'] ?? 0),
            'valor_pequena' => BrDecimalImport::parse($row['VALOR_PEQUENA'] ?? 0, 4),
            'valor_media' => BrDecimalImport::parse($row['VALOR_MEDIA'] ?? 0, 4),
            'valor_grande' => BrDecimalImport::parse($row['VALOR_GRANDE'] ?? 0, 4),
            'tempo_espera' => (int) ($row['TEMPO_ESPERA'] ?? 0),
            'complemento' => trim((string) ($row['COMPLEMENTO'] ?? '')) ?: null,
            'is_remedio' => $this->snToBool($row['REMEDIO'] ?? 'N'),
            'principio_ativo_id' => filled($row['FK_PRINCIPIO_ATIVO'] ?? null) ? (int) $row['FK_PRINCIPIO_ATIVO'] : null,
            'aplicacao' => trim((string) ($row['APLICACAO'] ?? '')) ?: null,
            'promo_data_inicio' => $this->mapDate($row['INICIO_PROMOCAO'] ?? null),
            'promo_data_fim' => $this->mapDate($row['FIM_PROMOCAO'] ?? null),
            'promo_preco_venda' => BrDecimalImport::parse($row['PRECO_PROMO_VAREJO'] ?? 0),
            'promo_preco_atacado' => BrDecimalImport::parse($row['PRECO_PROMO_ATACADO'] ?? 0),
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

                if ($payload['codigo'] === '' || $payload['descricao'] === '') {
                    $stats['skipped']++;

                    continue;
                }

                $existing = Product::query()->where('codigo', $payload['codigo'])->first();

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
                    Product::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function mapGrupo(array $row): string
    {
        if (! empty($row['GRUPO_NOME'])) {
            return Str::upper(trim((string) $row['GRUPO_NOME']));
        }

        if (! empty($row['VIRTUAL_GRUPO'])) {
            return Str::upper(trim((string) $row['VIRTUAL_GRUPO']));
        }

        $grupoId = $row['GRUPO'] ?? null;

        if ($grupoId !== null && $grupoId !== '') {
            $nome = Grupo::query()->where('id', (int) $grupoId)->value('nome')
                ?? Grupo::query()->where('nome', (string) $grupoId)->value('nome');

            if ($nome) {
                return Str::upper($nome);
            }
        }

        return 'DIVERSOS';
    }

    protected function mapTipoProduto(mixed $tipo): string
    {
        $value = trim((string) $tipo);

        if ($value === '') {
            return '00';
        }

        if (strlen($value) === 2 && ctype_digit($value)) {
            return $value;
        }

        return substr($value, 0, 2);
    }

    protected function normalizeBarcode(mixed $value): ?string
    {
        $barcode = trim((string) ($value ?? ''));

        if ($barcode === '' || strtoupper($barcode) === 'SEM GTIN') {
            return 'SEM GTIN';
        }

        return preg_replace('/\D/', '', $barcode) ?: 'SEM GTIN';
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
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
