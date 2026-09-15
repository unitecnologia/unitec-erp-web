<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Erp\ProductOrphanQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductsPurgeOrphansCommand extends Command
{
    protected $signature = 'products:purge-orphans
                            {--dry-run : Apenas lista/conta candidatos, sem apagar}
                            {--limit=0 : Limite de exclusões (0 = todos)}
                            {--chunk=500 : Tamanho do lote de delete}';

    protected $description = 'Remove produtos sem vínculo comercial/estoque (órfãos) da base local';

    public function handle(ProductOrphanQuery $orphans): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(1, (int) $this->option('chunk'));

        $base = $orphans->builder()->orderBy('products.id');
        $total = (clone $base)->count();
        $productsTotal = Product::query()->count();

        $this->info("Produtos no cadastro: {$productsTotal}");
        $this->info("Órfãos candidatos: {$total}");

        if ($total === 0) {
            $this->info('Nada a fazer.');

            return self::SUCCESS;
        }

        $sample = (clone $base)
            ->limit(20)
            ->get(['products.id', 'products.codigo', 'products.descricao']);

        $this->table(
            ['id', 'codigo', 'descricao'],
            $sample->map(fn (Product $p): array => [
                $p->id,
                (string) $p->codigo,
                mb_substr((string) $p->descricao, 0, 60),
            ])->all()
        );

        if ($sample->count() < $total) {
            $this->comment('… amostra das primeiras 20 linhas.');
        }

        if ($dryRun) {
            $this->warn('[dry-run] Nenhuma exclusão realizada.');

            return self::SUCCESS;
        }

        $toDelete = $limit > 0 ? min($total, $limit) : $total;
        $this->info("Excluindo {$toDelete} produto(s)…");

        $deleted = 0;

        try {
            DB::transaction(function () use ($orphans, $chunk, $toDelete, &$deleted): void {
                while ($deleted < $toDelete) {
                    $batchSize = min($chunk, $toDelete - $deleted);
                    $ids = $orphans->builder()
                        ->orderBy('products.id')
                        ->limit($batchSize)
                        ->pluck('products.id')
                        ->all();

                    if ($ids === []) {
                        break;
                    }

                    $n = Product::query()->whereIn('id', $ids)->delete();
                    $deleted += $n;

                    if ($n === 0) {
                        break;
                    }
                }
            });
        } catch (Throwable $e) {
            $this->error('Falha ao excluir: '.$e->getMessage());

            return self::FAILURE;
        }

        $remainingOrphans = $orphans->builder()->count();
        $remainingProducts = Product::query()->count();

        $this->info("Excluídos: {$deleted}");
        $this->info("Produtos restantes: {$remainingProducts}");
        $this->info("Órfãos restantes: {$remainingOrphans}");

        return self::SUCCESS;
    }
}
