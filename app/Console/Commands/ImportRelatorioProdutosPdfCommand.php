<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Product;
use App\Models\ProductEmpresaPreco;
use App\Models\ProductEstoqueSaldo;
use App\Support\Erp\ErpDataSyncVersion;
use App\Support\Erp\Import\RelatorioProdutosPdfParser;
use App\Support\Erp\ProductEmpresaPrecoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportRelatorioProdutosPdfCommand extends Command
{
    protected $signature = 'erp:importar-produtos-pdf
        {caminho : Caminho do PDF Relatório de Produtos}
        {--fase=1 : 1=substitui catálogo (só EAN completo); 2=acrescenta pulados}
        {--replace : Obrigatório na fase 1 (substitui o catálogo; não apaga vendas/clientes)}';

    protected $description = 'Importa EAN, descrição e preço de venda de um relatório PDF';

    public function handle(RelatorioProdutosPdfParser $parser, ProductEmpresaPrecoService $precos): int
    {
        $caminho = (string) $this->argument('caminho');
        $fase = (int) $this->option('fase');

        if ($fase !== 1 && $fase !== 2) {
            $this->error('Use --fase=1 ou --fase=2.');

            return self::FAILURE;
        }

        if (! is_file($caminho)) {
            $this->error('Arquivo não encontrado: '.$caminho);

            return self::FAILURE;
        }

        if ($fase === 1) {
            return $this->importarFase1($parser, $precos, $caminho);
        }

        return $this->importarFase2($parser, $precos, $caminho);
    }

    private function importarFase1(
        RelatorioProdutosPdfParser $parser,
        ProductEmpresaPrecoService $precos,
        string $caminho,
    ): int {
        if (! $this->option('replace')) {
            $this->error('Fase 1: use --replace para substituir o catálogo de produtos.');

            return self::FAILURE;
        }

        $this->info('Lendo PDF (fase 1)…');
        $resultado = $parser->parseArquivo($caminho, 1);
        $itens = $resultado['importar'];
        $pulados = $resultado['pulados'];

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
                    'codigo_barras' => $item['ean'],
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
        $this->info('Pulados (fase 2): '.count($pulados));
        $this->imprimirMotivos($pulados);

        return self::SUCCESS;
    }

    private function importarFase2(
        RelatorioProdutosPdfParser $parser,
        ProductEmpresaPrecoService $precos,
        string $caminho,
    ): int {
        if ($this->option('replace')) {
            $this->error('Fase 2 não usa --replace (append-only; não trunca o catálogo).');

            return self::FAILURE;
        }

        $this->info('Lendo PDF (fase 2)…');
        $resultado = $parser->parseArquivo($caminho, 2);
        $itens = $resultado['importar'];
        $pulados = $resultado['pulados'];

        $empresaIds = Empresa::query()->where('ativo', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $eanExistentes = Product::query()
            ->whereNotNull('codigo_barras')
            ->where('codigo_barras', '!=', '')
            ->pluck('codigo_barras')
            ->mapWithKeys(fn ($ean): array => [(string) $ean => true])
            ->all();

        $proximoCodigo = (int) (Product::query()
            ->selectRaw('MAX(CAST(codigo AS UNSIGNED)) as max_codigo')
            ->value('max_codigo') ?? 0);

        $inseridos = 0;
        $eanJaExiste = 0;

        Product::withoutEvents(function () use (
            $itens,
            $precos,
            $empresaIds,
            &$eanExistentes,
            &$proximoCodigo,
            &$inseridos,
            &$eanJaExiste,
        ): void {
            foreach ($itens as $item) {
                $ean = (string) ($item['ean'] ?? '');

                if ($ean !== '' && isset($eanExistentes[$ean])) {
                    $eanJaExiste++;

                    continue;
                }

                $proximoCodigo++;
                $descricao = mb_strtoupper($item['descricao'], 'UTF-8');
                $product = Product::query()->create([
                    'codigo' => (string) $proximoCodigo,
                    'codigo_barras' => $ean,
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

                if ($ean !== '') {
                    $eanExistentes[$ean] = true;
                }

                $inseridos++;
            }
        });

        ErpDataSyncVersion::bump(ErpDataSyncVersion::CHANNEL_PRODUCTS);

        $this->info('Inseridos (fase 2): '.$inseridos);
        $this->info('EAN já existe: '.$eanJaExiste);
        $this->info('Ainda pulados: '.count($pulados));
        $this->imprimirMotivos($pulados);

        return self::SUCCESS;
    }

    /**
     * @param  list<array{motivo: string, trecho: string}>  $pulados
     */
    private function imprimirMotivos(array $pulados): void
    {
        $porMotivo = [];
        foreach ($pulados as $p) {
            $motivo = $p['motivo'];
            $porMotivo[$motivo] = ($porMotivo[$motivo] ?? 0) + 1;
        }

        foreach ($porMotivo as $motivo => $qtd) {
            $this->line('  - '.$motivo.': '.$qtd);
        }
    }
}
