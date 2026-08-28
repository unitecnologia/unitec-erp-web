<?php

namespace App\Support\Erp\Import;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Support\Erp\ErpDataSyncVersion;
use App\Support\Erp\ProductEmpresaPrecoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

final class ProdutosPlanilhaImportService
{
    /**
     * @param list<string> $camposAtualizar
     * @return array{arquivo: string, total: int, validos: int, novos: int, duplicados_planilha: int, existentes: int, atualizaveis: int, sem_codigo: int, estoque_negativo_zerado: int, erros: list<string>}
     */
    public function analisar(string $path, array $camposAtualizar = [], bool $zerarTabela = false): array
    {
        $rows = $this->readRows($path);
        $existingCodes = $zerarTabela
            ? []
            : Product::query()
                ->pluck('codigo')
                ->map(fn ($codigo): string => $this->normalizeCode($codigo))
                ->filter()
                ->flip()
                ->all();

        $seen = [];
        $result = [
            'arquivo' => basename($path),
            'total' => 0,
            'validos' => 0,
            'novos' => 0,
            'duplicados_planilha' => 0,
            'existentes' => 0,
            'atualizaveis' => 0,
            'sem_codigo' => 0,
            'estoque_negativo_zerado' => 0,
            'erros' => [],
        ];

        foreach ($rows as $line => $row) {
            $result['total']++;
            $codigo = $this->normalizeCode($row['codigo'] ?? '');
            $descricao = trim((string) ($row['descricao'] ?? ''));

            if ($codigo === '' || $descricao === '') {
                $result['sem_codigo']++;
                if (count($result['erros']) < 10) {
                    $result['erros'][] = "Linha {$line}: código ou descrição ausente.";
                }

                continue;
            }

            if (isset($seen[$codigo])) {
                $result['duplicados_planilha']++;

                continue;
            }

            $seen[$codigo] = true;
            $result['validos']++;

            if (isset($existingCodes[$codigo])) {
                $result['existentes']++;
                if ($camposAtualizar !== []) {
                    $result['atualizaveis']++;
                }

                continue;
            }

            if ($this->estoqueDaLinhaEhNegativo($row)) {
                $result['estoque_negativo_zerado']++;
            }

            $result['novos']++;
        }

        return $result;
    }

    /**
     * @param list<string> $camposAtualizar
     * @return array{importados: int, atualizados: int, existentes: int, duplicados_planilha: int, sem_codigo: int, estoque_negativo_zerado: int, erros: list<string>}
     */
    public function importar(string $path, array $camposAtualizar = [], bool $zerarTabela = false): array
    {
        $rows = $this->readRows($path);
        $allowedFields = array_flip(self::camposAtualizaveis());
        $camposAtualizar = array_values(array_filter(
            array_unique($camposAtualizar),
            static fn (string $campo): bool => isset($allowedFields[$campo]),
        ));

        $result = [
            'importados' => 0,
            'atualizados' => 0,
            'existentes' => 0,
            'duplicados_planilha' => 0,
            'sem_codigo' => 0,
            'estoque_negativo_zerado' => 0,
            'erros' => [],
        ];
        $seen = [];
        $lookups = $this->empresaImportLookups();

        DB::transaction(function () use ($rows, &$seen, &$result, $camposAtualizar, $zerarTabela, $lookups): void {
            if ($zerarTabela) {
                Product::query()->delete();
            }

            $precoService = app(ProductEmpresaPrecoService::class);

            foreach ($rows as $line => $row) {
                $codigo = $this->normalizeCode($row['codigo'] ?? '');
                $descricao = trim((string) ($row['descricao'] ?? ''));

                if ($codigo === '' || $descricao === '') {
                    $result['sem_codigo']++;
                    if (count($result['erros']) < 10) {
                        $result['erros'][] = "Linha {$line}: código ou descrição ausente.";
                    }

                    continue;
                }

                if (isset($seen[$codigo])) {
                    $result['duplicados_planilha']++;

                    continue;
                }

                $seen[$codigo] = true;

                $row = $this->applyEmpresaColumnFallbacks($row);
                $estoque = $this->decimal($row['estoque'] ?? 0);
                if ($estoque < 0) {
                    $estoque = 0;
                    $result['estoque_negativo_zerado']++;
                }

                $data = $this->mapProduct($row, $codigo, $descricao, $estoque);
                $product = Product::query()
                    ->select(['id', 'codigo'])
                    ->where('codigo', $codigo)
                    ->first();

                if ($product instanceof Product) {
                    $result['existentes']++;

                    if ($camposAtualizar !== []) {
                        $updates = array_intersect_key($data, array_flip($camposAtualizar));
                        if ($updates !== []) {
                            Product::query()->whereKey($product->id)->update($updates);
                            $result['atualizados']++;
                        }
                    }

                    $this->syncEmpresaPrecosEEstoques($product, $row, $lookups, $precoService, $result, $line);

                    continue;
                }

                $product = Product::query()->create($data);
                $this->syncEmpresaPrecosEEstoques($product, $row, $lookups, $precoService, $result, $line);
                $result['importados']++;
            }
        });

        if ($result['importados'] > 0 || $result['atualizados'] > 0) {
            ErpDataSyncVersion::bump(ErpDataSyncVersion::CHANNEL_PRODUCTS);
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public static function camposAtualizaveis(): array
    {
        return [
            'descricao', 'referencia', 'codigo_barras', 'codigo_barras_caixa', 'tipo_produto', 'marca', 'grupo',
            'unidade', 'preco_compra', 'ult_compra', 'ult_compra_anterior', 'pct_custos', 'preco_custo',
            'preco_custo_anterior', 'e_medio', 'pct_lucro', 'preco_venda', 'preco_venda_anterior',
            'qtd_atacado', 'preco_atacado', 'preco_especial', 'estoque', 'estoque_minimo', 'peso_kg',
            'localizacao', 'validade', 'lote', 'info_adicionais', 'ncm', 'ncm_descricao', 'cest',
            'cfop_interno', 'origem', 'cst_icms', 'csosn', 'aliq_icms', 'cfop_externo', 'cst_externo',
            'csosn_externo', 'aliq_icms_externo', 'cst_entrada', 'cst_saida', 'cst_cofins', 'aliq_pis',
            'aliq_cofins', 'cst_ipi', 'cod_enq_ipi', 'aliq_ipi', 'fcp_pct', 'mva_pct', 'mva_normal',
            'reducao_base_pct', 'icms_diferido', 'aliq_deson', 'motivo_desoneracao', 'tipo_tributacao',
            'cod_beneficio', 'iva_cst', 'cclass_trib', 'cclass_trib_descricao', 'aliq_ibs_uf', 'aliq_cbs',
            'aliq_ibs_mun', 'aliq_adrem_ibs', 'aliq_adrem_cbs', 'reducao_cbs', 'reducao_ibs',
            'tributacao_monofasica', 'glp_pct', 'gnn_pct', 'gni_pct', 'peso_liq', 'anp_code', 'issqn',
            'prefixo_balanca', 'produto_pesado', 'tem_info_nutricional', 'nutri_porcao_qtd',
            'nutri_porcao_unidade', 'nutri_medida_inteiro', 'nutri_medida_fracao', 'nutri_medida_tipo',
            'nutri_valor_energetico', 'nutri_carboidratos', 'nutri_proteinas', 'nutri_gorduras_totais',
            'nutri_gorduras_saturadas', 'nutri_gorduras_trans', 'nutri_fibra', 'nutri_sodio',
            'is_restaurante', 'tipo_restaurante', 'menu_id', 'tipo_alimento', 'qtd_sabores', 'valor_pequena',
            'valor_media', 'valor_grande', 'complemento', 'tempo_espera', 'is_remedio', 'principio_ativo_id',
            'aplicacao', 'ativo', 'is_fiscal', 'paga_comissao', 'preco_variavel', 'is_composicao', 'is_servico',
            'is_grade', 'usa_tab_preco', 'is_combustivel', 'usa_imei', 'contr_est_grade', 'mostrar_no_app',
            'promo_data_inicio', 'promo_data_fim', 'promo_preco_venda', 'promo_preco_atacado',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function gruposCamposAtualizaveis(): array
    {
        return [
            'Cadastro' => ['descricao', 'referencia', 'codigo_barras', 'codigo_barras_caixa', 'tipo_produto', 'marca', 'grupo', 'unidade', 'localizacao', 'validade', 'lote', 'info_adicionais', 'ativo'],
            'Preços e estoque' => ['preco_compra', 'ult_compra', 'ult_compra_anterior', 'pct_custos', 'preco_custo', 'preco_custo_anterior', 'e_medio', 'pct_lucro', 'preco_venda', 'preco_venda_anterior', 'qtd_atacado', 'preco_atacado', 'preco_especial', 'estoque', 'estoque_minimo', 'peso_kg'],
            'Fiscal' => ['ncm', 'ncm_descricao', 'cest', 'cfop_interno', 'origem', 'cst_icms', 'csosn', 'aliq_icms', 'cfop_externo', 'cst_externo', 'csosn_externo', 'aliq_icms_externo', 'cst_entrada', 'cst_saida', 'cst_cofins', 'aliq_pis', 'aliq_cofins', 'cst_ipi', 'cod_enq_ipi', 'aliq_ipi', 'fcp_pct', 'mva_pct', 'mva_normal', 'reducao_base_pct', 'icms_diferido', 'aliq_deson', 'motivo_desoneracao', 'tipo_tributacao', 'cod_beneficio', 'iva_cst', 'cclass_trib', 'cclass_trib_descricao'],
            'Recursos especiais' => ['produto_pesado', 'tem_info_nutricional', 'nutri_porcao_qtd', 'nutri_porcao_unidade', 'nutri_medida_inteiro', 'nutri_medida_fracao', 'nutri_medida_tipo', 'nutri_valor_energetico', 'nutri_carboidratos', 'nutri_proteinas', 'nutri_gorduras_totais', 'nutri_gorduras_saturadas', 'nutri_gorduras_trans', 'nutri_fibra', 'nutri_sodio', 'is_restaurante', 'tipo_restaurante', 'menu_id', 'tipo_alimento', 'qtd_sabores', 'valor_pequena', 'valor_media', 'valor_grande', 'complemento', 'tempo_espera', 'is_remedio', 'principio_ativo_id', 'aplicacao', 'is_composicao', 'is_servico', 'is_grade', 'usa_tab_preco', 'is_combustivel', 'usa_imei', 'contr_est_grade', 'mostrar_no_app'],
        ];
    }

    /**
     * @return array{importados: int, existentes: int, duplicados_planilha: int, sem_codigo: int, estoque_negativo_zerado: int, erros: list<string>}
     */
    private function importarLegado(string $path): array
    {
        $rows = $this->readRows($path);
        $existingCodes = Product::query()
            ->pluck('codigo')
            ->map(fn ($codigo): string => $this->normalizeCode($codigo))
            ->filter()
            ->flip()
            ->all();

        $result = [
            'importados' => 0,
            'existentes' => 0,
            'duplicados_planilha' => 0,
            'sem_codigo' => 0,
            'estoque_negativo_zerado' => 0,
            'erros' => [],
        ];
        $seen = [];

        DB::transaction(function () use ($rows, &$existingCodes, &$seen, &$result): void {
            foreach ($rows as $line => $row) {
                $codigo = $this->normalizeCode($row['codigo'] ?? '');
                $descricao = trim((string) ($row['descricao'] ?? ''));

                if ($codigo === '' || $descricao === '') {
                    $result['sem_codigo']++;
                    if (count($result['erros']) < 10) {
                        $result['erros'][] = "Linha {$line}: código ou descrição ausente.";
                    }

                    continue;
                }

                if (isset($seen[$codigo])) {
                    $result['duplicados_planilha']++;

                    continue;
                }

                $seen[$codigo] = true;

                if (isset($existingCodes[$codigo])) {
                    $result['existentes']++;

                    continue;
                }

                $estoque = $this->decimal($row['estoque'] ?? 0);
                if ($estoque < 0) {
                    $estoque = 0;
                    $result['estoque_negativo_zerado']++;
                }

                Product::query()->create($this->mapProduct($row, $codigo, $descricao, $estoque));
                $existingCodes[$codigo] = true;
                $result['importados']++;
            }
        });

        if ($result['importados'] > 0) {
            ErpDataSyncVersion::bump(ErpDataSyncVersion::CHANNEL_PRODUCTS);
        }

        return $result;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readRows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Arquivo de planilha não encontrado.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir a planilha XLSX.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

            if ($sheet === false) {
                throw new RuntimeException('A planilha não possui a primeira aba para importação.');
            }

            $xml = simplexml_load_string($sheet);
            if ($xml === false) {
                throw new RuntimeException('A planilha XLSX está inválida.');
            }

            $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $xml->registerXPathNamespace('x', $namespace);
            $lines = $xml->xpath('//x:sheetData/x:row') ?: [];
            if (count($lines) === 0) {
                throw new RuntimeException('A planilha não possui linhas para importar.');
            }

            $headers = [];
            $rows = [];

            $rowIndex = 0;
            foreach ($lines as $line) {
                $rowIndex++;
                $lineNumber = (int) ($line['r'] ?? $rowIndex);
                $values = [];
                $line->registerXPathNamespace('x', $namespace);

                foreach ($line->xpath('./x:c') ?: [] as $cell) {
                    $reference = (string) ($cell['r'] ?? '');
                    $column = preg_replace('/\d+/', '', $reference) ?: '';
                    $values[$column] = $this->cellValue($cell, $sharedStrings);
                }

                if ($rowIndex === 1) {
                    foreach ($values as $column => $value) {
                        $headers[$column] = trim(mb_strtolower((string) $value, 'UTF-8'));
                    }

                    continue;
                }

                $mapped = [];
                foreach ($headers as $column => $header) {
                    if ($header !== '') {
                        $mapped[$header] = $values[$column] ?? '';
                    }
                }

                if ($mapped !== []) {
                    $rows[$lineNumber] = $mapped;
                }
            }

            if (! in_array('codigo', $headers, true)) {
                throw new RuntimeException('A planilha não possui a coluna obrigatória "codigo".');
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xmlData = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlData === false) {
            return [];
        }

        $xml = simplexml_load_string($xmlData);
        if ($xml === false) {
            return [];
        }

        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $strings = [];

        foreach ($xml->children($namespace)->si as $item) {
            $children = $item->children($namespace);
            $text = '';

            foreach ($children->t as $part) {
                $text .= (string) $part;
            }

            foreach ($children->r as $run) {
                $text .= (string) $run->children($namespace)->t;
            }

            $strings[] = trim($text);
        }

        return $strings;
    }

    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            $inline = $cell->children($namespace)->is ?? null;

            return $inline ? trim((string) $inline) : '';
        }

        $children = $cell->children($namespace);
        $value = (string) ($children->v ?? '');

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    private function mapProduct(array $row, string $codigo, string $descricao, float $estoque): array
    {
        $stringFields = [
            'referencia', 'codigo_barras', 'codigo_barras_caixa', 'tipo_produto', 'marca', 'grupo', 'unidade',
            'cfop_interno', 'cst_icms', 'csosn', 'cfop_externo', 'cst_externo', 'csosn_externo', 'cst_entrada',
            'cst_saida', 'cst_cofins', 'cst_ipi', 'cod_enq_ipi', 'motivo_desoneracao', 'tipo_tributacao',
            'cod_beneficio', 'iva_cst', 'cclass_trib', 'cclass_trib_descricao', 'tributacao_monofasica',
            'anp_code', 'issqn', 'prefixo_balanca', 'nutri_porcao_unidade', 'nutri_medida_tipo', 'tipo_restaurante',
            'tipo_alimento', 'complemento', 'aplicacao', 'ncm', 'ncm_descricao', 'cest', 'localizacao', 'lote',
            'info_adicionais',
        ];
        $decimalFields = [
            'preco_compra', 'ult_compra', 'ult_compra_anterior', 'pct_custos', 'preco_custo', 'e_medio',
            'preco_custo_anterior', 'pct_lucro', 'aliq_icms', 'aliq_icms_externo', 'aliq_pis', 'aliq_cofins',
            'aliq_ipi', 'fcp_pct', 'mva_pct', 'mva_normal', 'reducao_base_pct', 'icms_diferido', 'aliq_deson',
            'aliq_ibs_uf', 'aliq_cbs', 'aliq_ibs_mun', 'aliq_adrem_ibs', 'aliq_adrem_cbs', 'reducao_cbs',
            'reducao_ibs', 'glp_pct', 'gnn_pct', 'gni_pct', 'peso_liq', 'nutri_porcao_qtd',
            'nutri_valor_energetico', 'nutri_carboidratos', 'nutri_proteinas', 'nutri_gorduras_totais',
            'nutri_gorduras_saturadas', 'nutri_gorduras_trans', 'nutri_fibra', 'nutri_sodio', 'preco_venda',
            'preco_venda_anterior', 'qtd_atacado', 'preco_atacado', 'preco_especial', 'estoque_minimo', 'peso_kg',
            'valor_pequena', 'valor_media', 'valor_grande', 'tempo_espera', 'promo_preco_venda', 'promo_preco_atacado',
        ];
        $integerFields = [
            'ult_fornecedor_id', 'origem', 'menu_id', 'qtd_sabores', 'principio_ativo_id', 'nutri_medida_inteiro',
            'nutri_medida_fracao',
        ];
        $booleanFields = [
            'produto_pesado', 'tem_info_nutricional', 'is_restaurante', 'is_remedio', 'ativo', 'is_fiscal',
            'paga_comissao', 'preco_variavel', 'is_composicao', 'is_servico', 'is_grade', 'usa_tab_preco',
            'is_combustivel', 'usa_imei', 'contr_est_grade', 'mostrar_no_app',
        ];

        $data = [
            'codigo' => $codigo,
            'descricao' => mb_strtoupper($descricao, 'UTF-8'),
            'estoque' => $estoque,
            'ativo' => true,
            'unidade' => 'UN',
        ];

        foreach ($stringFields as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $data[$field] = $field === 'grupo' || $field === 'marca'
                    ? mb_strtoupper($value, 'UTF-8')
                    : $value;
            }
        }

        foreach ($decimalFields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== '') {
                $data[$field] = $this->decimal($row[$field]);
            }
        }

        foreach ($integerFields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== '') {
                $data[$field] = (int) $this->decimal($row[$field]);
            }
        }

        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== '') {
                $data[$field] = $this->boolean($row[$field]);
            }
        }

        foreach (['validade', 'promo_data_inicio', 'promo_data_fim'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function estoqueDaLinhaEhNegativo(array $row): bool
    {
        if ($this->decimal($row['estoque'] ?? 0) < 0) {
            return true;
        }

        foreach ($row as $key => $value) {
            if (! is_string($key) || ! preg_match('/^estoque_e\d+$/', $key)) {
                continue;
            }

            if ($value !== '' && $this->decimal($value) < 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Preenche colunas globais a partir da matriz (_e1) e soma estoques _e*.
     *
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function applyEmpresaColumnFallbacks(array $row): array
    {
        foreach (ProductEmpresaPrecoService::FIELDS as $field) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                continue;
            }

            $fromMatriz = trim((string) ($row[$field.'_e1'] ?? ''));
            if ($fromMatriz !== '') {
                $row[$field] = $fromMatriz;

                continue;
            }

            foreach ($row as $key => $value) {
                if (! is_string($key) || trim((string) $value) === '') {
                    continue;
                }

                if (preg_match('/^'.preg_quote($field, '/').'_e(\d+)$/', $key) === 1) {
                    $row[$field] = (string) $value;
                    break;
                }
            }
        }

        if (trim((string) ($row['preco_compra'] ?? '')) === '' && trim((string) ($row['preco_custo'] ?? '')) !== '') {
            $row['preco_compra'] = (string) $row['preco_custo'];
        }

        if (trim((string) ($row['estoque'] ?? '')) === '') {
            $soma = 0.0;
            $temEstoqueEmpresa = false;
            foreach ($row as $key => $value) {
                if (! is_string($key) || preg_match('/^estoque_e\d+$/', $key) !== 1) {
                    continue;
                }

                $temEstoqueEmpresa = true;
                $qtd = $this->decimal($value);
                $soma += $qtd < 0 ? 0.0 : $qtd;
            }

            if ($temEstoqueEmpresa) {
                $row['estoque'] = (string) $soma;
            }
        }

        return $row;
    }

    /**
     * @return array{empresas: array<int, int>, estoques: array<int, int>}
     */
    private function empresaImportLookups(): array
    {
        $empresas = [];
        foreach (Empresa::query()->where('ativo', true)->get(['id', 'codigo']) as $empresa) {
            $empresas[(int) $empresa->codigo] = (int) $empresa->id;
        }

        $estoques = [];
        if (Schema::hasTable('estoques')) {
            $rows = Estoque::query()
                ->where('ativo', true)
                ->orderByRaw('CAST(codigo AS UNSIGNED)')
                ->orderBy('codigo')
                ->get(['id', 'empresa_id']);

            foreach ($rows as $estoque) {
                $empresaId = (int) $estoque->empresa_id;
                if ($empresaId > 0 && ! isset($estoques[$empresaId])) {
                    $estoques[$empresaId] = (int) $estoque->id;
                }
            }
        }

        return ['empresas' => $empresas, 'estoques' => $estoques];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{empresas: array<int, int>, estoques: array<int, int>}  $lookups
     * @param  array{importados: int, atualizados: int, existentes: int, duplicados_planilha: int, sem_codigo: int, estoque_negativo_zerado: int, erros: list<string>}  $result
     */
    private function syncEmpresaPrecosEEstoques(
        Product $product,
        array $row,
        array $lookups,
        ProductEmpresaPrecoService $precoService,
        array &$result,
        int $line,
    ): void {
        $codigosNaLinha = [];
        foreach (array_keys($row) as $key) {
            if (! is_string($key) || preg_match('/_e(\d+)$/', $key, $match) !== 1) {
                continue;
            }

            $codigosNaLinha[(int) $match[1]] = true;
        }

        foreach (array_keys($codigosNaLinha) as $empresaCodigo) {
            $empresaId = $lookups['empresas'][$empresaCodigo] ?? 0;
            if ($empresaId <= 0) {
                if (count($result['erros']) < 10) {
                    $result['erros'][] = "Linha {$line}: colunas _e{$empresaCodigo} ignoradas (empresa código {$empresaCodigo} não cadastrada).";
                }

                continue;
            }

            $suffix = '_e'.$empresaCodigo;
            $hasSuffix = false;
            $prices = [];
            foreach (ProductEmpresaPrecoService::FIELDS as $field) {
                $key = $field.$suffix;
                if (! array_key_exists($key, $row)) {
                    $prices[$field] = 0.0;

                    continue;
                }

                $hasSuffix = true;
                $raw = trim((string) $row[$key]);
                $prices[$field] = $raw === '' ? 0.0 : round($this->decimal($raw), 2);
            }

            if ($hasSuffix) {
                if (($prices['preco_compra'] ?? 0) <= 0 && ($prices['preco_custo'] ?? 0) > 0) {
                    $prices['preco_compra'] = $prices['preco_custo'];
                }

                $precoService->upsert($product, $empresaId, $prices);
            }

            $estoqueRaw = $row['estoque'.$suffix] ?? null;
            if ($estoqueRaw === null || trim((string) $estoqueRaw) === '') {
                continue;
            }

            $qtd = $this->decimal($estoqueRaw);
            if ($qtd < 0) {
                $qtd = 0;
                $result['estoque_negativo_zerado']++;
            }

            $estoqueId = $lookups['estoques'][$empresaId] ?? 0;
            if ($estoqueId <= 0 || ! Schema::hasTable('product_estoque_saldos')) {
                continue;
            }

            ProductEstoqueSaldo::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'estoque_id' => $estoqueId,
                ],
                ['quantidade' => round($qtd, 3)],
            );
        }
    }

    private function normalizeCode(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_replace('/\.0+$/', '', $value) ?? '';
    }

    private function decimal(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function boolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'sim', 'yes'], true);
    }
}
