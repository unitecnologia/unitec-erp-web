<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Product;
use App\Models\ProductEmpresaPreco;
use App\Models\ProductEstoqueSaldo;
use App\Support\Erp\ErpDataSyncVersion;
use App\Support\Erp\ProductEmpresaPrecoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportProdutosCsvCommand extends Command
{
    protected $signature = 'erp:importar-produtos-csv
        {caminho : Caminho do CSV (codigo_barras;descricao;preco_venda)}
        {--replace : Obrigatório (substitui o catálogo; não apaga vendas/clientes)}';

    protected $description = 'Importa código de barras, descrição e preço de venda de um CSV (; / preço BR)';

    public function handle(ProductEmpresaPrecoService $precos): int
    {
        if (! $this->option('replace')) {
            $this->error('Use --replace para substituir o catálogo de produtos.');

            return self::FAILURE;
        }

        $caminho = (string) $this->argument('caminho');

        if (! is_file($caminho)) {
            $this->error('Arquivo não encontrado: '.$caminho);

            return self::FAILURE;
        }

        $this->info('Lendo CSV…');
        $itens = $this->lerCsv($caminho);

        if ($itens === []) {
            $this->error('Nenhuma linha válida no CSV.');

            return self::FAILURE;
        }

        $empresaIds = Empresa::query()->where('ativo', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if (Schema::hasTable('product_empresa_precos')) {
            ProductEmpresaPreco::query()->delete();
        }

        if (Schema::hasTable('product_estoque_saldos')) {
            ProductEstoqueSaldo::query()->delete();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('products')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Product::withoutEvents(function () use ($itens, $precos, $empresaIds): void {
            $codigoSeq = 0;
            foreach ($itens as $item) {
                $codigoSeq++;
                $descricao = mb_strtoupper($item['descricao'], 'UTF-8');
                $product = Product::query()->create([
                    'codigo' => (string) $codigoSeq,
                    'codigo_barras' => $item['codigo_barras'],
                    'descricao' => $descricao,
                    'unidade' => 'UN',
                    'preco_venda' => $item['preco'],
                    'estoque' => 0,
                    'ativo' => true,
                ]);

                $precos->replicate($product, [
                    'preco_compra' => 0,
                    'pct_custos' => 0,
                    'preco_custo' => 0,
                    'pct_lucro' => 0,
                    'preco_venda' => $item['preco'],
                    'preco_atacado' => 0,
                    'preco_especial' => 0,
                ], $empresaIds);
            }
        });

        ErpDataSyncVersion::bump(ErpDataSyncVersion::CHANNEL_PRODUCTS);

        $this->info('Importados: '.count($itens));

        return self::SUCCESS;
    }

    /**
     * @return list<array{codigo_barras: string, descricao: string, preco: float}>
     */
    private function lerCsv(string $caminho): array
    {
        $handle = fopen($caminho, 'rb');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(
            static fn ($h): string => mb_strtolower(trim((string) $h), 'UTF-8'),
            $header,
        );
        $idxBarras = array_search('codigo_barras', $header, true);
        $idxDesc = array_search('descricao', $header, true);
        $idxPreco = array_search('preco_venda', $header, true);

        if ($idxBarras === false || $idxDesc === false || $idxPreco === false) {
            fclose($handle);
            $this->error('CSV precisa das colunas: codigo_barras;descricao;preco_venda');

            return [];
        }

        $seen = [];
        $itens = [];
        $duplicados = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $barras = trim((string) ($row[$idxBarras] ?? ''));
            $descricao = trim((string) ($row[$idxDesc] ?? ''));
            $precoRaw = trim((string) ($row[$idxPreco] ?? ''));

            if ($barras === '' || $descricao === '') {
                continue;
            }

            if (isset($seen[$barras])) {
                $duplicados++;

                continue;
            }

            $seen[$barras] = true;
            $itens[] = [
                'codigo_barras' => $barras,
                'descricao' => $descricao,
                'preco' => $this->parsePrecoBr($precoRaw),
            ];
        }

        fclose($handle);

        if ($duplicados > 0) {
            $this->line("Duplicatas de código de barras ignoradas: {$duplicados}");
        }

        return $itens;
    }

    private function parsePrecoBr(string $valor): float
    {
        $valor = trim($valor);
        if ($valor === '') {
            return 0.0;
        }

        $valor = str_replace(['.', ' '], ['', ''], $valor);
        $valor = str_replace(',', '.', $valor);

        return round((float) $valor, 2);
    }
}
