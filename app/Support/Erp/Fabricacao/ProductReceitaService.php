<?php

namespace App\Support\Erp\Fabricacao;

use App\Models\Product;
use App\Models\ProductReceita;
use App\Models\ProductReceitaItem;
use App\Support\Erp\ErpSchema;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cadastro de receitas de fabricação (sem movimentar estoque nem alterar preços).
 */
final class ProductReceitaService
{
    public function tabelaDisponivel(): bool
    {
        return ErpSchema::hasTable('product_receitas')
            && ErpSchema::hasTable('product_receita_itens');
    }

    /**
     * @param  list<array{component_product_id: int, quantidade: float|int|string}>  $itens
     */
    public function sync(Product $acabado, float $qtdProduzida, array $itens, bool $ativo = true): ?ProductReceita
    {
        if (! $this->tabelaDisponivel()) {
            throw new DomainException('Tabelas de receita de fabricação não estão disponíveis.');
        }

        $acabadoId = (int) $acabado->getKey();

        if ($acabadoId <= 0) {
            throw new DomainException('Produto acabado inválido.');
        }

        $normalized = $this->normalizeItens($itens);

        if ($normalized === []) {
            return $this->clear($acabadoId);
        }

        $this->assertAcabadoPermitido($acabado);
        $this->assertRendimento($qtdProduzida);
        $this->assertItensValidos($acabadoId, $normalized);

        return DB::transaction(function () use ($acabadoId, $qtdProduzida, $normalized, $ativo): ProductReceita {
            $receita = ProductReceita::query()->firstOrNew(['product_id' => $acabadoId]);
            $receita->qtd_produzida = round($qtdProduzida, 3);
            $receita->ativo = $ativo;
            $receita->save();

            $keepIds = [];

            foreach ($normalized as $item) {
                $row = ProductReceitaItem::query()->updateOrCreate(
                    [
                        'receita_id' => (int) $receita->id,
                        'component_product_id' => $item['component_product_id'],
                    ],
                    [
                        'quantidade' => $item['quantidade'],
                    ],
                );
                $keepIds[] = (int) $row->id;
            }

            ProductReceitaItem::query()
                ->where('receita_id', (int) $receita->id)
                ->whereNotIn('id', $keepIds)
                ->delete();

            return $receita->fresh(['itens']) ?? $receita;
        });
    }

    public function clear(int $acabadoProductId): ?ProductReceita
    {
        if (! $this->tabelaDisponivel() || $acabadoProductId <= 0) {
            return null;
        }

        $receitaIds = ProductReceita::query()
            ->where('product_id', $acabadoProductId)
            ->pluck('id')
            ->all();

        if ($receitaIds !== []) {
            ProductReceitaItem::query()->whereIn('receita_id', $receitaIds)->delete();
            ProductReceita::query()->whereIn('id', $receitaIds)->delete();
        }

        return null;
    }

    /**
     * @param  list<array{component_product_id?: int, quantidade?: float|int|string, custo_unitario?: float}>  $itens
     * @return array{custo_total: float, custo_unitario_produzido: float, linhas: list<array<string, mixed>>}
     */
    public function estimarCusto(float $qtdProduzida, array $itens): array
    {
        $componentIds = [];

        foreach ($itens as $item) {
            $id = (int) ($item['component_product_id'] ?? 0);
            if ($id > 0) {
                $componentIds[] = $id;
            }
        }

        $products = $componentIds === []
            ? collect()
            : Product::query()
                ->whereIn('id', array_values(array_unique($componentIds)))
                ->get(['id', 'codigo', 'descricao', 'unidade', 'preco_custo', 'preco_compra', 'preco_venda'])
                ->keyBy('id');

        $linhas = [];

        foreach ($itens as $item) {
            $id = (int) ($item['component_product_id'] ?? 0);
            $qtd = round((float) ($item['quantidade'] ?? 0), 3);
            /** @var Product|null $product */
            $product = $id > 0 ? ($products->get($id) ?? $products->get((string) $id)) : null;
            $custoUnit = array_key_exists('custo_unitario', $item)
                ? round((float) $item['custo_unitario'], 2)
                : ($product ? ProductReceitaCusto::unitario($product) : 0.0);
            $custoLinha = round($qtd * $custoUnit, 2);

            $linhas[] = [
                'component_product_id' => $id,
                'codigo' => $product?->codigo,
                'descricao' => $product?->descricao,
                'unidade' => $product?->unidade,
                'quantidade' => $qtd,
                'custo_unitario' => $custoUnit,
                'custo_total' => $custoLinha,
                // Garante que preço de venda nunca entra no cálculo (apenas exposição de auditoria).
                'preco_venda_ignorado' => $product ? round((float) ($product->preco_venda ?? 0), 2) : 0.0,
            ];
        }

        $totais = ProductReceitaCusto::estimar($qtdProduzida, $linhas);

        return [
            'custo_total' => $totais['custo_total'],
            'custo_unitario_produzido' => $totais['custo_unitario_produzido'],
            'linhas' => $linhas,
        ];
    }

    /**
     * @param  list<array{component_product_id: int, quantidade: float|int|string}>  $itens
     * @return list<array{component_product_id: int, quantidade: float}>
     */
    private function normalizeItens(array $itens): array
    {
        $out = [];

        foreach ($itens as $item) {
            $id = (int) ($item['component_product_id'] ?? 0);
            $qtd = round((float) ($item['quantidade'] ?? 0), 3);

            if ($id <= 0 || $qtd <= 0) {
                continue;
            }

            if (isset($out[$id])) {
                $out[$id]['quantidade'] = round($out[$id]['quantidade'] + $qtd, 3);
            } else {
                $out[$id] = [
                    'component_product_id' => $id,
                    'quantidade' => $qtd,
                ];
            }
        }

        return array_values($out);
    }

    private function assertAcabadoPermitido(Product $acabado): void
    {
        if ($acabado->is_servico) {
            throw new DomainException('Serviço não pode ter receita de fabricação.');
        }

        if (! ($acabado->ativo ?? true)) {
            throw new DomainException('Produto acabado inativo não pode receber receita de fabricação.');
        }
    }

    private function assertRendimento(float $qtdProduzida): void
    {
        if ($qtdProduzida <= 0) {
            throw new DomainException('Informe o rendimento da receita (quantidade produzida maior que zero).');
        }
    }

    /**
     * @param  list<array{component_product_id: int, quantidade: float}>  $itens
     */
    private function assertItensValidos(int $acabadoId, array $itens): void
    {
        $ids = array_map(static fn (array $item): int => $item['component_product_id'], $itens);

        $components = Product::query()
            ->whereIn('id', $ids)
            ->get(['id', 'codigo', 'descricao', 'ativo', 'is_servico'])
            ->keyBy('id');

        foreach ($itens as $item) {
            $id = $item['component_product_id'];
            /** @var Product|null $component */
            $component = $components->get($id) ?? $components->get((string) $id);

            if ($component === null) {
                throw new DomainException('Ingrediente não encontrado (id '.$id.').');
            }

            if ($id === $acabadoId) {
                throw new DomainException('O produto acabado não pode ser ingrediente da própria receita.');
            }

            if ($component->is_servico) {
                throw new DomainException('Serviço não pode ser ingrediente: '.trim((string) ($component->descricao ?: $component->codigo)));
            }

            if (! ($component->ativo ?? false)) {
                throw new DomainException('Ingrediente inativo não permitido: '.trim((string) ($component->descricao ?: $component->codigo)));
            }

            if (ProductReceitaCycleGuard::wouldCreateCycle($acabadoId, $id)) {
                throw new DomainException(
                    'Receita circular não permitida com: '.trim((string) ($component->descricao ?: $component->codigo))
                );
            }
        }
    }
}
