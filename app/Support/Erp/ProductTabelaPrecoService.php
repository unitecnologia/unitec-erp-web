<?php

namespace App\Support\Erp;

use App\Models\PriceTable;
use App\Models\Product;
use App\Models\ProductPriceTableItem;
use App\Support\Erp\Promocao\PromocaoPrecoService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas de preço simplificadas: Varejo / Atacado / Especial.
 * A presença em product_price_table_items habilita o nível; o valor vem da Precificação.
 */
final class ProductTabelaPrecoService
{
    public const NIVEL_VAREJO = 'varejo';

    public const NIVEL_ATACADO = 'atacado';

    public const NIVEL_ESPECIAL = 'especial';

    /** @var list<string> */
    public const NIVEIS = [self::NIVEL_VAREJO, self::NIVEL_ATACADO, self::NIVEL_ESPECIAL];

    /** @var array<string, array{codigo: string, descricao: string}> */
    private const PADRAO = [
        self::NIVEL_VAREJO => ['codigo' => '1', 'descricao' => 'VAREJO'],
        self::NIVEL_ATACADO => ['codigo' => '2', 'descricao' => 'ATACADO'],
        self::NIVEL_ESPECIAL => ['codigo' => '3', 'descricao' => 'ESPECIAL'],
    ];

    /**
     * @return array<string, int> nivel => price_table_id
     */
    public function tabelasPadraoIds(): array
    {
        return Cache::remember('erp.price_tables.padrao_ids', 300, function (): array {
            $this->ensureTabelasPadrao();

            $rows = PriceTable::query()
                ->whereIn('codigo', ['1', '2', '3'])
                ->get(['id', 'codigo', 'descricao']);

            $map = [];

            foreach ($rows as $row) {
                $nivel = $this->nivelDaTabela($row);

                if ($nivel !== null && ! isset($map[$nivel])) {
                    $map[$nivel] = (int) $row->id;
                }
            }

            foreach (self::NIVEIS as $nivel) {
                if (! isset($map[$nivel])) {
                    $meta = self::PADRAO[$nivel];
                    $id = (int) PriceTable::query()->insertGetId([
                        'codigo' => $meta['codigo'],
                        'descricao' => $meta['descricao'],
                        'ativo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $map[$nivel] = $id;
                }
            }

            return $map;
        });
    }

    public function tabelaIdDoNivel(string $nivel): ?int
    {
        $nivel = $this->normalizarNivel($nivel);

        return $nivel ? ($this->tabelasPadraoIds()[$nivel] ?? null) : null;
    }

    public function nivelDaTabelaId(?int $priceTableId): ?string
    {
        if (! $priceTableId) {
            return null;
        }

        foreach ($this->tabelasPadraoIds() as $nivel => $id) {
            if ((int) $id === (int) $priceTableId) {
                return $nivel;
            }
        }

        $table = PriceTable::query()->find($priceTableId, ['id', 'codigo', 'descricao']);

        return $table ? $this->nivelDaTabela($table) : null;
    }

    public function nivelDaTabela(?PriceTable $table): ?string
    {
        if (! $table) {
            return null;
        }

        $blob = mb_strtoupper(trim((string) ($table->codigo ?? '').' '.(string) ($table->descricao ?? '')), 'UTF-8');

        if (str_contains($blob, 'ESPECIAL') || (string) ($table->codigo ?? '') === '3') {
            return self::NIVEL_ESPECIAL;
        }

        if (str_contains($blob, 'ATACADO') || (string) ($table->codigo ?? '') === '2') {
            return self::NIVEL_ATACADO;
        }

        if (
            str_contains($blob, 'VAREJO')
            || str_contains($blob, 'PADRAO')
            || str_contains($blob, 'PADRÃO')
            || (string) ($table->codigo ?? '') === '1'
        ) {
            return self::NIVEL_VAREJO;
        }

        return null;
    }

    public function normalizarNivel(mixed $nivel): ?string
    {
        $nivel = mb_strtolower(trim((string) $nivel), 'UTF-8');

        return in_array($nivel, self::NIVEIS, true) ? $nivel : null;
    }

    /**
     * Níveis cadastrados no produto (independe da flag — usado no formulário).
     *
     * @return list<string>
     */
    public function niveisConfigurados(Product $product): array
    {
        $ids = $this->tabelasPadraoIds();
        $habilitados = [];

        $items = $product->relationLoaded('priceTableItems')
            ? $product->priceTableItems
            : $product->priceTableItems()->get(['id', 'product_id', 'price_table_id']);

        $enabledIds = $items
            ->pluck('price_table_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach (self::NIVEIS as $nivel) {
            $tableId = (int) ($ids[$nivel] ?? 0);

            if ($tableId > 0 && in_array($tableId, $enabledIds, true)) {
                $habilitados[] = $nivel;
            }
        }

        return $habilitados;
    }

    /**
     * Níveis efetivos na venda: só com usa_tab_preco ativo.
     *
     * @return list<string>
     */
    public function niveisHabilitados(Product $product): array
    {
        if (! ($product->usa_tab_preco ?? false)) {
            return [];
        }

        return $this->niveisConfigurados($product);
    }

    public function habilitaNivel(Product $product, string $nivel): bool
    {
        $nivel = $this->normalizarNivel($nivel);

        return $nivel !== null && in_array($nivel, $this->niveisHabilitados($product), true);
    }

    /**
     * Opções para escolha na venda (somente níveis marcados no produto).
     *
     * @return list<array{nivel: string, price_table_id: int, codigo: string, descricao: string, label: string, preco: float}>
     */
    public function opcoesParaProduto(Product $product, float $quantidade = 1, ?int $empresaId = null): array
    {
        $ids = $this->tabelasPadraoIds();
        $opcoes = [];

        foreach ($this->niveisHabilitados($product) as $nivel) {
            $meta = self::PADRAO[$nivel];
            $tableId = (int) ($ids[$nivel] ?? 0);
            $preco = $this->resolvePrecoNivel($product, $nivel, $quantidade, $empresaId);

            $opcoes[] = [
                'nivel' => $nivel,
                'price_table_id' => $tableId,
                'codigo' => $meta['codigo'],
                'descricao' => $meta['descricao'],
                'label' => $meta['codigo'].' - '.$meta['descricao'],
                'preco' => $preco,
            ];
        }

        return $opcoes;
    }

    public function resolvePrecoNivel(
        Product $product,
        string $nivel,
        float $quantidade = 1,
        ?int $empresaId = null,
    ): float {
        $nivel = $this->normalizarNivel($nivel) ?? self::NIVEL_VAREJO;
        $empresaId ??= (int) (session('erp_empresa_id') ?? 0);
        $precos = app(ProductEmpresaPrecoService::class)->resolve(
            $product,
            $empresaId > 0 ? $empresaId : null
        );

        return match ($nivel) {
            self::NIVEL_ATACADO => $this->precoAtacado($product, $precos),
            self::NIVEL_ESPECIAL => $this->precoEspecial($product, $precos),
            default => $this->precoVarejo($product, $precos, $quantidade, $empresaId),
        };
    }

    /**
     * @param  array{preco_venda: float, preco_atacado: float, preco_especial?: float}  $precos
     */
    private function precoVarejo(Product $product, array $precos, float $quantidade, int $empresaId): float
    {
        $hoje = Carbon::today();

        if ($empresaId > 0) {
            $campanha = app(PromocaoPrecoService::class)->precoVarejoAtivo($product, $empresaId, $hoje);

            if ($campanha !== null) {
                return round($campanha, 2);
            }
        }

        if ($this->emPromocaoLegado($product, $hoje)) {
            return round((float) $product->promo_preco_venda, 2);
        }

        return round((float) ($precos['preco_venda'] ?? 0), 2);
    }

    /**
     * @param  array{preco_venda: float, preco_atacado: float, preco_especial?: float}  $precos
     */
    private function precoAtacado(Product $product, array $precos): float
    {
        $atacado = (float) ($precos['preco_atacado'] ?? 0);

        if ($atacado > 0) {
            return round($atacado, 2);
        }

        return round((float) ($precos['preco_venda'] ?? 0), 2);
    }

    /**
     * @param  array{preco_venda: float, preco_atacado: float, preco_especial?: float}  $precos
     */
    private function precoEspecial(Product $product, array $precos): float
    {
        $especial = (float) ($precos['preco_especial'] ?? $product->preco_especial ?? 0);

        if ($especial > 0) {
            return round($especial, 2);
        }

        return round((float) ($precos['preco_venda'] ?? 0), 2);
    }

    private function emPromocaoLegado(Product $product, Carbon $data): bool
    {
        if (blank($product->promo_data_inicio) || blank($product->promo_data_fim)) {
            return false;
        }

        if ((float) ($product->promo_preco_venda ?? 0) <= 0) {
            return false;
        }

        return $data->between(
            Carbon::parse($product->promo_data_inicio)->startOfDay(),
            Carbon::parse($product->promo_data_fim)->endOfDay(),
        );
    }

    /**
     * Persiste as tabelas marcadas. Com a flag desligada não apaga nada —
     * só desativa o uso na venda (usa_tab_preco = false).
     *
     * @param  list<string>  $niveis
     */
    public function syncNiveis(Product $product, array $niveis, bool $atualizarSelecao = true): void
    {
        if (! $atualizarSelecao) {
            return;
        }

        $ids = $this->tabelasPadraoIds();
        $keepTableIds = [];

        foreach ($niveis as $nivel) {
            $nivel = $this->normalizarNivel($nivel);

            if (! $nivel) {
                continue;
            }

            $tableId = (int) ($ids[$nivel] ?? 0);

            if ($tableId <= 0) {
                continue;
            }

            ProductPriceTableItem::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'price_table_id' => $tableId,
                ],
                [],
            );
            $keepTableIds[] = $tableId;
        }

        $query = $product->priceTableItems();

        if ($keepTableIds !== []) {
            $query->whereNotIn('price_table_id', $keepTableIds)->delete();

            return;
        }

        // Seleção vazia só é válida com flag desligada (aba oculta): não mexe nos itens.
        if (! ($product->usa_tab_preco ?? false)) {
            return;
        }

        $query->delete();
    }

    /**
     * @param  Collection<int, Product>|iterable<int, Product>|Product  $products
     */
    public function carregarItens(Collection|iterable|Product $products): void
    {
        if ($products instanceof Product) {
            $products = new \Illuminate\Database\Eloquent\Collection([$products]);
        } elseif (! $products instanceof \Illuminate\Database\Eloquent\Collection) {
            $products = new \Illuminate\Database\Eloquent\Collection(
                collect($products)->filter(fn ($p): bool => $p instanceof Product)->all()
            );
        }

        if ($products->isEmpty()) {
            return;
        }

        $ids = $this->tabelasPadraoIds();
        $tableIds = array_values($ids);

        $products->load([
            'priceTableItems' => fn ($q) => $q->whereIn('price_table_id', $tableIds),
        ]);
    }

    public function ensureTabelasPadrao(): void
    {
        if (! Schema::hasTable('price_tables')) {
            return;
        }

        foreach (self::PADRAO as $meta) {
            $exists = DB::table('price_tables')
                ->where('codigo', $meta['codigo'])
                ->exists();

            if ($exists) {
                DB::table('price_tables')
                    ->where('codigo', $meta['codigo'])
                    ->update([
                        'descricao' => $meta['descricao'],
                        'ativo' => true,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('price_tables')->insert([
                'codigo' => $meta['codigo'],
                'descricao' => $meta['descricao'],
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function labelNivel(string $nivel): string
    {
        $nivel = $this->normalizarNivel($nivel) ?? self::NIVEL_VAREJO;
        $meta = self::PADRAO[$nivel];

        return $meta['codigo'].' - '.$meta['descricao'];
    }
}
