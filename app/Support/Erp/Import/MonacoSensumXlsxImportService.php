<?php

namespace App\Support\Erp\Import;

use App\Models\Empresa;
use App\Models\Person;
use App\Models\PriceTable;
use App\Models\Product;
use App\Models\ProductPriceTableItem;
use App\Support\Erp\ErpDataSyncVersion;
use App\Support\Erp\MunicipioLookupService;
use App\Support\Erp\ProductEmpresaPrecoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class MonacoSensumXlsxImportService
{
    /** @var list<string> */
    private const PRODUCT_CHILD_TABLES = [
        'product_price_table_items',
        'product_empresa_precos',
        'product_estoque_saldos',
        'product_grades',
        'product_compositions',
        'product_imeis',
        'product_serials',
        'product_lotes',
        'product_price_histories',
    ];

    /** @var list<string> */
    private const PRODUCT_LINK_ITEM_TABLES = [
        'pdv_venda_itens',
        'nfe_itens',
        'orcamento_itens',
        'compra_itens',
        'nfse_itens',
        'ordem_servico_itens',
        'devolucao_venda_itens',
        'devolucao_compra_itens',
        'nota_fornecedor_itens',
        'venda_itens',
        'entrega_itens',
        'promocao_itens',
        'estoque_reservas',
    ];

    /**
     * @return array{
     *   dry_run: bool,
     *   produtos_ativos_planilha: int,
     *   produtos_desativados_pulados: int,
     *   produtos_criados: int,
     *   ncm_aplicados: int,
     *   ncm_sem_produto: int,
     *   tabela_itens: int,
     *   pessoas_criadas: int,
     *   pessoas_atualizadas: int,
     *   pessoas_puladas: int
     * }
     */
    public function import(
        string $pathProdutos,
        string $pathTabelaPreco,
        string $pathPessoas,
        bool $dryRun = false,
    ): array {
        if (app()->environment('production')) {
            throw new RuntimeException('Bloqueado: APP_ENV=production.');
        }

        foreach ([$pathProdutos, $pathTabelaPreco, $pathPessoas] as $path) {
            if (! is_file($path)) {
                throw new RuntimeException('Arquivo não encontrado: '.$path);
            }
        }

        $precoRows = $this->readTabelaPrecoRows($pathTabelaPreco);
        $ncmByCodigo = $this->readNcmByCodigo($pathProdutos);
        $pessoaRows = $this->readPessoaRows($pathPessoas);

        $ativos = [];
        $desativados = 0;
        foreach ($precoRows as $row) {
            if ($row['desativado']) {
                $desativados++;

                continue;
            }
            if ($row['codigo'] === '' || $row['descricao'] === '') {
                continue;
            }
            // Mantém a primeira ocorrência ativa do código.
            if (! isset($ativos[$row['codigo']])) {
                $ativos[$row['codigo']] = $row;
            }
        }

        $stats = [
            'dry_run' => $dryRun,
            'produtos_ativos_planilha' => count($ativos),
            'produtos_desativados_pulados' => $desativados,
            'produtos_criados' => 0,
            'ncm_aplicados' => 0,
            'ncm_sem_produto' => 0,
            'tabela_itens' => 0,
            'pessoas_criadas' => 0,
            'pessoas_atualizadas' => 0,
            'pessoas_puladas' => 0,
        ];

        if ($dryRun) {
            foreach (array_keys($ncmByCodigo) as $codigo) {
                if (isset($ativos[$codigo])) {
                    $stats['ncm_aplicados']++;
                } else {
                    $stats['ncm_sem_produto']++;
                }
            }
            $stats['produtos_criados'] = count($ativos);
            $stats['tabela_itens'] = count($ativos);
            foreach ($pessoaRows as $pessoa) {
                if ($pessoa['codigo'] === '' || $pessoa['nome_razao'] === '') {
                    $stats['pessoas_puladas']++;

                    continue;
                }
                if (Person::isCodigoConsumidorFinal($pessoa['codigo'])) {
                    $stats['pessoas_puladas']++;

                    continue;
                }
                $exists = Person::query()->where('codigo', $pessoa['codigo'])->exists();
                $exists ? $stats['pessoas_atualizadas']++ : $stats['pessoas_criadas']++;
            }

            return $stats;
        }

        $empresaIds = Empresa::query()
            ->where('ativo', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $precoService = app(ProductEmpresaPrecoService::class);

        // TRUNCATE no MySQL faz commit implícito — wipe fora de transaction.
        $this->wipeProductsCatalog();

        $priceTable3 = $this->resolveStandardPriceTable('3');
        // Tabelas padrão do sistema (1 VAREJO, 2 ATACADO, 3 ESPECIAL) — não altera descrições.
        // Códigos extras da planilha Monaco → sem tabela nas pessoas.

        /** @var array<string, Product> $productsByCodigo */
        $productsByCodigo = [];

        Product::withoutEvents(function () use (
            $ativos,
            $ncmByCodigo,
            $empresaIds,
            $precoService,
            $priceTable3,
            &$productsByCodigo,
            &$stats,
        ): void {
            foreach ($ativos as $codigo => $row) {
                $ncm = $ncmByCodigo[$codigo]['ncm'] ?? null;
                $pesoKg = $ncmByCodigo[$codigo]['peso_kg'] ?? null;
                $pesoLiq = $ncmByCodigo[$codigo]['peso_liq'] ?? null;

                $product = Product::query()->create([
                    'codigo' => $codigo,
                    'descricao' => $row['descricao'],
                    'referencia' => $codigo,
                    'unidade' => $row['unidade'],
                    'preco_custo' => $row['custo'],
                    'e_medio' => $row['custo'],
                    'preco_venda' => $row['preco'],
                    'estoque' => 0,
                    'ativo' => true,
                    'ncm' => $ncm ?? '00000000',
                    'peso_kg' => $pesoKg ?? 0,
                    'peso_liq' => $pesoLiq ?? 0,
                ]);

                if ($ncm !== null) {
                    $stats['ncm_aplicados']++;
                }

                $precoService->replicate($product, [
                    'preco_compra' => 0,
                    'pct_custos' => 0,
                    'preco_custo' => $row['custo'],
                    'pct_lucro' => 0,
                    'preco_venda' => $row['preco'],
                    'preco_atacado' => 0,
                    'preco_especial' => 0,
                ], $empresaIds);

                ProductPriceTableItem::query()->create([
                    'product_id' => $product->id,
                    'price_table_id' => $priceTable3->id,
                    'valor' => $row['preco'],
                    'fator' => 1,
                ]);

                $productsByCodigo[$codigo] = $product;
                $stats['produtos_criados']++;
                $stats['tabela_itens']++;
            }
        });

        foreach ($ncmByCodigo as $codigo => $_info) {
            if (! isset($productsByCodigo[$codigo])) {
                $stats['ncm_sem_produto']++;
            }
        }

        Person::withoutEvents(function () use ($pessoaRows, &$stats): void {
            $municipioMap = $this->municipioIbgeMap();

            foreach ($pessoaRows as $pessoa) {
                if ($pessoa['codigo'] === '' || $pessoa['nome_razao'] === '') {
                    $stats['pessoas_puladas']++;

                    continue;
                }

                if (Person::isCodigoConsumidorFinal($pessoa['codigo'])) {
                    $stats['pessoas_puladas']++;

                    continue;
                }

                $temRepresentante = $pessoa['representante'] !== null && $pessoa['representante'] !== '';
                $cidadeCodigo = $this->resolveIbgeCodigo(
                    $municipioMap,
                    $pessoa['cidade_nome'] ?? null,
                    $pessoa['uf'] ?? null,
                );
                $payload = [
                    'codigo' => $pessoa['codigo'],
                    'pessoa_tipo' => $pessoa['pessoa_tipo'],
                    'nome_razao' => $pessoa['nome_razao'],
                    'apelido_fantasia' => $pessoa['apelido_fantasia'],
                    'cpf_cnpj' => $pessoa['cpf_cnpj'],
                    'rg_ie' => $pessoa['rg_ie'],
                    'tipo_contribuinte' => $this->mapTipoContribuinte($pessoa['rg_ie'] ?? null),
                    'endereco' => $pessoa['endereco'],
                    'numero' => $pessoa['numero'],
                    'bairro' => $pessoa['bairro'],
                    'cidade_nome' => $pessoa['cidade_nome'],
                    'cidade_codigo' => $cidadeCodigo,
                    'uf' => $pessoa['uf'],
                    'cep' => $pessoa['cep'],
                    'email' => $pessoa['email'],
                    'fone1' => $pessoa['fone1'],
                    'celular1' => $pessoa['celular1'],
                    'representante' => $pessoa['representante'],
                    // Sem representante na planilha → fornecedor (não cliente).
                    'is_cliente' => $temRepresentante,
                    'is_fornecedor' => ! $temRepresentante,
                    'is_funcionario' => $pessoa['is_funcionario'],
                    'ativo' => true,
                    // Clientes ficam sem tabela de preço (padrão do vendedor).
                    'price_table_id' => null,
                ];

                $existing = Person::query()->where('codigo', $pessoa['codigo'])->first();
                try {
                    if ($existing) {
                        $existing->update($payload);
                        $stats['pessoas_atualizadas']++;
                    } else {
                        Person::query()->create($payload);
                        $stats['pessoas_criadas']++;
                    }
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    $stats['pessoas_puladas']++;
                    \Illuminate\Support\Facades\Log::warning('MonacoSensumXlsxImport: CPF/CNPJ duplicado — pessoa pulada.', [
                        'codigo' => $pessoa['codigo'] ?? null,
                        'cpf_cnpj' => $payload['cpf_cnpj'] ?? null,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });

        ErpDataSyncVersion::bump(ErpDataSyncVersion::CHANNEL_PRODUCTS);
        ErpDataSyncVersion::bump(ErpDataSyncVersion::CHANNEL_PEOPLE);

        return $stats;
    }

    private function wipeProductsCatalog(): void
    {
        foreach (self::PRODUCT_CHILD_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        foreach (self::PRODUCT_LINK_ITEM_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if (Schema::hasTable('estoque_movimentacoes')) {
            DB::table('estoque_movimentacoes')->delete();
        }

        if (Schema::hasTable('ajustes_estoque')) {
            DB::table('ajustes_estoque')->delete();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('products')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Resolve tabela de preço padrão sem alterar a descrição existente.
     * Códigos canônicos: 1=VAREJO, 2=ATACADO, 3=ESPECIAL.
     */
    private function resolveStandardPriceTable(string $codigo): PriceTable
    {
        $defaults = [
            '1' => 'VAREJO',
            '2' => 'ATACADO',
            '3' => 'ESPECIAL',
        ];

        $table = PriceTable::query()->where('codigo', $codigo)->first();
        if ($table) {
            if (! $table->ativo) {
                $table->update(['ativo' => true]);
            }

            return $table->fresh() ?? $table;
        }

        return PriceTable::query()->create([
            'codigo' => $codigo,
            'descricao' => $defaults[$codigo] ?? ('TABELA '.$codigo),
            'ativo' => true,
        ]);
    }

    /**
     * @return list<array{codigo: string, descricao: string, unidade: string, custo: float, preco: float, desativado: bool}>
     */
    private function readTabelaPrecoRows(string $path): array
    {
        $rows = $this->readXlsxAssoc($path);
        $out = [];

        foreach ($rows as $row) {
            $codigo = $this->normalizeCode($this->pick($row, ['codigo', 'cod']));
            $descricao = Str::upper(trim($this->pick($row, ['produto', 'descricao', 'descricao_produto'])));
            $unidade = Str::upper(trim($this->pick($row, ['un', 'unidade', 'un_'])) ?: 'UN');
            $custo = $this->decimal($this->pick($row, ['custo_medio', 'custo', 'ult_custo']));
            $preco = $this->decimal($this->pick($row, ['preco_inf', 'preco', 'preco_venda', 'preco_infin']));
            $desativadoRaw = trim($this->pick($row, ['desativado_em', 'desativado']));

            $out[] = [
                'codigo' => $codigo,
                'descricao' => $descricao,
                'unidade' => $unidade !== '' ? $unidade : 'UN',
                'custo' => $custo,
                'preco' => $preco,
                'desativado' => $desativadoRaw !== '',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{ncm: string, peso_kg: ?float, peso_liq: ?float}>
     */
    private function readNcmByCodigo(string $path): array
    {
        $rows = $this->readXlsxAssoc($path);
        $out = [];

        foreach ($rows as $row) {
            $codigo = $this->normalizeCode($this->pick($row, ['codigo', 'cod']));
            if ($codigo === '') {
                continue;
            }

            $ncmRaw = preg_replace('/\D/', '', $this->pick($row, ['ncm'])) ?? '';
            $ncm = $ncmRaw !== ''
                ? str_pad(substr($ncmRaw, 0, 8), 8, '0', STR_PAD_LEFT)
                : '00000000';

            $pesoKg = $this->pick($row, ['peso_bruto', 'peso_kg']);
            $pesoLiq = $this->pick($row, ['peso_liquido', 'peso_liq']);

            $out[$codigo] = [
                'ncm' => $ncm,
                'peso_kg' => $pesoKg !== '' ? $this->decimal($pesoKg) : null,
                'peso_liq' => $pesoLiq !== '' ? $this->decimal($pesoLiq) : null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{
     *   codigo: string,
     *   nome_razao: string,
     *   apelido_fantasia: ?string,
     *   pessoa_tipo: string,
     *   cpf_cnpj: ?string,
     *   rg_ie: ?string,
     *   endereco: ?string,
     *   numero: ?string,
     *   bairro: ?string,
     *   cidade_nome: ?string,
     *   uf: ?string,
     *   cep: ?string,
     *   email: ?string,
     *   fone1: ?string,
     *   celular1: ?string,
     *   is_funcionario: bool,
     *   representante: ?string,
     *   tabela_preco_codigo: string
     * }>
     */
    private function readPessoaRows(string $path): array
    {
        $rows = $this->readXlsxAssoc($path);
        $out = [];

        foreach ($rows as $row) {
            $codigo = $this->normalizeCode($this->pick($row, ['cod_pessoa', 'codigo', 'cod']));
            $nome = Str::upper(trim($this->pick($row, ['nome', 'nome_razao', 'razao'])));
            $cnpj = trim($this->pick($row, ['cnpj']));
            $cpf = trim($this->pick($row, ['cpf']));
            $cpfCnpj = $cnpj !== '' ? $cnpj : ($cpf !== '' ? $cpf : null);
            $ie = trim($this->pick($row, ['inscricao_estadual', 'ie', 'rg_ie']));
            $funcRaw = trim($this->pick($row, ['funcionario']));
            $codRep = trim($this->pick($row, ['cod_rep', 'cod_representante']));
            $repNome = $this->nullableUpper($this->pick($row, ['representante', 'nome_representante']));
            if ($repNome === null && $codRep !== '') {
                $repNome = $codRep;
            }

            $out[] = [
                'codigo' => $codigo,
                'nome_razao' => $nome,
                'apelido_fantasia' => $this->nullableUpper($this->pick($row, ['nome_fantasia_apelido', 'fantasia', 'apelido'])),
                'pessoa_tipo' => $this->mapPessoaTipo($cpfCnpj ?? ''),
                'cpf_cnpj' => $cpfCnpj,
                'rg_ie' => $ie !== '' ? $ie : null,
                'endereco' => $this->nullableUpper($this->pick($row, ['logradouro', 'endereco'])),
                'numero' => ($n = trim($this->pick($row, ['numero', 'num']))) !== '' ? $n : null,
                'bairro' => $this->nullableUpper($this->pick($row, ['bairro'])),
                'cidade_nome' => $this->nullableUpper($this->pick($row, ['cidade', 'municipio'])),
                'uf' => ($uf = Str::upper(trim($this->pick($row, ['uf'])))) !== '' ? substr($uf, 0, 2) : null,
                'cep' => ($cep = preg_replace('/\D/', '', $this->pick($row, ['cep'])) ?? '') !== '' ? $cep : null,
                'email' => ($email = trim($this->pick($row, ['e_mail', 'email']))) !== '' ? $email : null,
                'fone1' => ($fone = trim($this->pick($row, ['fone', 'telefone', 'fone1']))) !== '' ? $fone : null,
                'celular1' => ($cel = trim($this->pick($row, ['celular', 'celular1']))) !== '' ? $cel : null,
                'is_funcionario' => $this->truthyFlag($funcRaw),
                'representante' => $repNome,
                'tabela_preco_codigo' => $this->normalizeCode($this->pick($row, ['cod_tabela_preco', 'codigo_tabela_preco', 'cod_tabela'])),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, string>>
     */
    private function readXlsxAssoc(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir a planilha XLSX: '.basename($path));
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false) {
                throw new RuntimeException('Planilha sem sheet1: '.basename($path));
            }

            $xml = simplexml_load_string($sheet);
            if ($xml === false) {
                throw new RuntimeException('XLSX inválido: '.basename($path));
            }

            $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $xml->registerXPathNamespace('x', $ns);
            $lines = $xml->xpath('//x:sheetData/x:row') ?: [];
            if ($lines === []) {
                throw new RuntimeException('Planilha vazia: '.basename($path));
            }

            $headers = [];
            $rows = [];
            $rowIndex = 0;

            foreach ($lines as $line) {
                $rowIndex++;
                $values = [];
                $line->registerXPathNamespace('x', $ns);

                foreach ($line->xpath('./x:c') ?: [] as $cell) {
                    $reference = (string) ($cell['r'] ?? '');
                    $column = preg_replace('/\d+/', '', $reference) ?: '';
                    $values[$column] = $this->cellValue($cell, $sharedStrings);
                }

                if ($rowIndex === 1) {
                    foreach ($values as $column => $value) {
                        $headers[$column] = $this->normalizeHeader((string) $value);
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
                    $rows[] = $mapped;
                }
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

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $strings = [];

        foreach ($xml->children($ns)->si as $item) {
            $children = $item->children($ns);
            $text = '';
            foreach ($children->t as $part) {
                $text .= (string) $part;
            }
            foreach ($children->r as $run) {
                $text .= (string) $run->children($ns)->t;
            }
            $strings[] = trim($text);
        }

        return $strings;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            $inline = $cell->children($ns)->is ?? null;

            return $inline ? trim((string) $inline) : '';
        }

        $children = $cell->children($ns);
        $value = (string) ($children->v ?? '');

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = Str::lower(Str::ascii(trim($header)));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        return match ($normalized) {
            'codigo', 'cod' => 'codigo',
            'cod_pessoa', 'cod_pessoas' => 'cod_pessoa',
            'descricao', 'produto' => $normalized === 'produto' ? 'produto' : 'descricao',
            'un', 'unidade' => $normalized === 'unidade' ? 'unidade' : 'un',
            'peso_bruto' => 'peso_bruto',
            'peso_liquido' => 'peso_liquido',
            'ncm' => 'ncm',
            'custo_medio' => 'custo_medio',
            'preco_inf', 'preco_infin' => 'preco_inf',
            'ult_custo' => 'ult_custo',
            'desativado_em' => 'desativado_em',
            'nome' => 'nome',
            'nome_fantasia_apelido' => 'nome_fantasia_apelido',
            'funcionario' => 'funcionario',
            'cnpj' => 'cnpj',
            'cpf' => 'cpf',
            'inscricao_estadual' => 'inscricao_estadual',
            'logradouro' => 'logradouro',
            'numero', 'num' => 'numero',
            'bairro' => 'bairro',
            'cidade' => 'cidade',
            'uf' => 'uf',
            'cep' => 'cep',
            'fone' => 'fone',
            'celular' => 'celular',
            'e_mail', 'email' => 'e_mail',
            'cod_rep', 'cod_representante' => 'cod_rep',
            'representante' => 'representante',
            'cod_tabela_preco', 'cod_tabela' => 'cod_tabela_preco',
            'tabela_de_preco_padrao' => 'tabela_de_preco_padrao',
            default => $normalized,
        };
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $keys
     */
    private function pick(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function normalizeCode(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Excel às vezes entrega "3.0"
        if (preg_match('/^\d+\.0+$/', $value)) {
            return (string) (int) $value;
        }

        return $value;
    }

    private function decimal(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return round((float) $value, 4);
    }

    private function mapPessoaTipo(string $cpfCnpj): string
    {
        $digits = preg_replace('/\D/', '', $cpfCnpj) ?? '';

        return strlen($digits) > 11 ? Person::PESSOA_JURIDICA : Person::PESSOA_FISICA;
    }

    private function nullableUpper(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? Str::upper($value) : null;
    }

    /**
     * @return array<string, string> chave "UF|NOME_NORMALIZADO" => codigo IBGE
     */
    private function municipioIbgeMap(): array
    {
        $svc = app(MunicipioLookupService::class);
        $map = [];

        foreach ($svc->todosMunicipios() as $municipio) {
            $key = $municipio['uf'].'|'.$this->normalizeMunicipioNome($municipio['nome']);
            $map[$key] = $municipio['codigo'];
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $municipioMap
     */
    private function resolveIbgeCodigo(array $municipioMap, ?string $cidadeNome, ?string $uf): ?string
    {
        $cidadeNome = trim((string) $cidadeNome);
        $uf = Str::upper(trim((string) $uf));

        if ($cidadeNome === '' || strlen($uf) !== 2) {
            return null;
        }

        return $municipioMap[$uf.'|'.$this->normalizeMunicipioNome($cidadeNome)] ?? null;
    }

    private function normalizeMunicipioNome(string $value): string
    {
        $value = Str::upper(trim($value));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }

    private function mapTipoContribuinte(?string $ie): string
    {
        $ie = Str::upper(trim((string) $ie));

        if ($ie === '' || $ie === '-') {
            return 'nao_contribuinte';
        }

        if (in_array($ie, ['ISENTO', 'ISENTA'], true)) {
            return 'isento';
        }

        return 'contribuinte';
    }

    private function truthyFlag(string $value): bool
    {
        $normalized = Str::upper(Str::ascii(trim($value)));

        if ($normalized === '' || in_array($normalized, ['N', 'NAO', '0', 'FALSE', 'F'], true)) {
            return false;
        }

        return true;
    }
}
