<?php

namespace App\Support\Erp\Pdv;

use App\Models\Product;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Pdv\PdvItemValidator;
use App\Support\Erp\Pdv\PdvProductPriceService;
use App\Support\Erp\Pdv\PdvStockService;

/**
 * Hot path do bip (Caixa Rápido): lookup + inclusão no cupom sem o Filament Page.
 * Regras alinhadas a ManagesPdvVenda (não duplicar fluxo fiscal/modais).
 */
final class PdvCaixaRapidoBipService
{
    public function __construct(
        private readonly PdvConfig $config,
        private readonly PdvProductPriceService $prices,
        private readonly PdvItemValidator $validator,
    ) {}

    public static function make(): self
    {
        $config = PdvConfig::make();
        $prices = new PdvProductPriceService($config);

        return new self(
            $config,
            $prices,
            new PdvItemValidator($config, $prices),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $cupomItens
     * @return array{
     *   status: 'added'|'not_found'|'delegate'|'caixa_fechado'|'empty',
     *   cupom: list<array<string, mixed>>,
     *   flash: array{qtd: ?string, preco: ?string, total: ?string},
     *   preview_name: ?string,
     *   preview_foto: ?string,
     *   produto_nome: ?string,
     *   reason: ?string
     * }
     */
    public function handle(string $codigo, array $cupomItens, bool $caixaAberto): array
    {
        $empty = [
            'cupom' => $cupomItens,
            'flash' => ['qtd' => null, 'preco' => null, 'total' => null],
            'preview_name' => null,
            'preview_foto' => null,
            'produto_nome' => null,
            'reason' => null,
        ];

        if (! $caixaAberto) {
            return ['status' => 'caixa_fechado'] + $empty;
        }

        $codigo = mb_strtoupper(trim($codigo), 'UTF-8');

        if ($codigo === '') {
            return ['status' => 'empty'] + $empty;
        }

        // Quantidade explícita (2*CODIGO) ou descrição → pai (mesmas regras/UI).
        if (str_contains($codigo, '*') || preg_match('/[A-Za-zÀ-ÿ]/u', $codigo) === 1) {
            return ['status' => 'delegate', 'reason' => 'termo_complexo'] + $empty;
        }

        $product = $this->findExactProduct($codigo);

        if (! $product) {
            return ['status' => 'not_found'] + $empty;
        }

        if ($product->is_grade || $product->preco_variavel || $product->usa_imei) {
            return ['status' => 'delegate', 'reason' => 'modal'] + $empty;
        }

        if ($product->produto_pesado && $this->config->lerPesoBalanca()) {
            return ['status' => 'delegate', 'reason' => 'balanca'] + $empty;
        }

        $quantidade = 1.0;
        $preco = $this->prices->resolvePrecoVenda($product, $quantidade);

        if ($product->is_composicao) {
            $msg = (new PdvStockService())->validaEstoqueComposicao($product, $quantidade);
            if ($msg) {
                return ['status' => 'delegate', 'reason' => 'composicao'] + $empty;
            }
        }

        if ($msg = $this->validator->validaQuantidade($quantidade)) {
            return ['status' => 'delegate', 'reason' => 'qtd'] + $empty;
        }

        if ($msg = $this->validator->validaPreco($product, $preco, $quantidade)) {
            return ['status' => 'delegate', 'reason' => 'preco'] + $empty;
        }

        if ($msg = $this->validator->validaEstoque($product, $quantidade, null)) {
            return ['status' => 'delegate', 'reason' => 'estoque'] + $empty;
        }

        if (! $product->ativo) {
            return ['status' => 'delegate', 'reason' => 'inativo'] + $empty;
        }

        $cupomItens = $this->addOrMerge($cupomItens, $product, $quantidade, $preco);
        $cupomItens = $this->recheckAtacado($cupomItens, (int) $product->id);

        $qtdFlash = fmod($quantidade, 1.0) === 0.0
            ? (string) (int) $quantidade
            : ErpMoney::formatBr($quantidade, 3);

        $foto = $product->fotoUrl();

        return [
            'status' => 'added',
            'cupom' => $cupomItens,
            'flash' => [
                'qtd' => $qtdFlash,
                'preco' => ErpMoney::formatBr($preco),
                'total' => ErpMoney::formatBr(round($quantidade * $preco, 2)),
            ],
            'preview_name' => mb_strtoupper((string) $product->descricao, 'UTF-8'),
            'preview_foto' => $foto,
            'produto_nome' => mb_strtoupper((string) $product->descricao, 'UTF-8'),
            'reason' => null,
        ];
    }

    private function findExactProduct(string $term): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term): void {
                $query->where('codigo', $term)
                    ->orWhere('codigo_barras', $term)
                    ->orWhere('codigo_barras_caixa', $term)
                    ->orWhere('referencia', $term);
            })
            ->first();
    }

    /**
     * @param  list<array<string, mixed>>  $cupomItens
     * @return list<array<string, mixed>>
     */
    private function addOrMerge(array $cupomItens, Product $product, float $quantidade, float $preco): array
    {
        foreach ($cupomItens as $index => $item) {
            if ((int) ($item['product_id'] ?? 0) === (int) $product->id
                && (int) ($item['product_grade_id'] ?? 0) === 0
                && empty($item['product_serial_id'])
                && round((float) ($item['preco'] ?? 0), 2) === round($preco, 2)) {
                $novaQuantidade = round((float) ($item['quantidade'] ?? 0) + $quantidade, 3);
                $cupomItens[$index]['quantidade'] = $novaQuantidade;
                $cupomItens[$index]['total'] = round($novaQuantidade * $preco, 2);

                return $cupomItens;
            }
        }

        $cupomItens[] = [
            'product_id' => $product->id,
            'product_grade_id' => null,
            'product_serial_id' => null,
            'codigo' => $product->codigo,
            'codigo_barras' => $product->codigo_barras ?? '',
            'descricao' => mb_strtoupper($product->descricao, 'UTF-8'),
            'unidade' => $product->unidade ?: 'UN',
            'quantidade' => $quantidade,
            'preco' => $preco,
            'preco_base' => $preco,
            'desconto' => 0.0,
            'acrescimo' => 0.0,
            'total' => round($quantidade * $preco, 2),
        ];

        if (! session()->has('erp.pdv.cupom_iniciado_em')) {
            session(['erp.pdv.cupom_iniciado_em' => now()->toIso8601String()]);
        }

        return $cupomItens;
    }

    /**
     * @param  list<array<string, mixed>>  $cupomItens
     * @return list<array<string, mixed>>
     */
    private function recheckAtacado(array $cupomItens, int $productId): array
    {
        $product = Product::query()->find($productId);

        if (! $product || (float) ($product->qtd_atacado ?? 0) <= 0) {
            return $cupomItens;
        }

        $totalQty = round(collect($cupomItens)
            ->where('product_id', $productId)
            ->sum(fn (array $item): float => (float) ($item['quantidade'] ?? 0)), 3);

        if ($totalQty < (float) $product->qtd_atacado) {
            return $cupomItens;
        }

        $preco = $this->prices->resolvePrecoVenda($product, $totalQty);

        foreach ($cupomItens as $index => $item) {
            if ((int) ($item['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $qtd = (float) ($item['quantidade'] ?? 0);
            $cupomItens[$index]['preco'] = $preco;
            $cupomItens[$index]['total'] = round($qtd * $preco, 2);
        }

        return $cupomItens;
    }
}
