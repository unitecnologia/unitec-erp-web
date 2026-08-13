<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Product;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\EstoqueReservaService;

trait ManagesOutrasSaidasProdutoInclusao
{
    public string $itemProdutoSearch = '';

    public ?int $itemPendingProductId = null;

    public string $itemEntryQtd = '1,000';

    public string $itemEntryPreco = '';

    public string $itemEntryTotalDisplay = '0,00';

    public bool $produtoLookupOpen = false;

    /** @var array<int, array{id:int,codigo:string,nome:string,atual:string,reservado:string,disponivel:string,preco:string}> */
    public array $produtoResults = [];

    public ?int $produtoSelecionadoIndex = null;

    public function updatedItemProdutoSearch(): void
    {
        $term = mb_strtoupper(trim($this->itemProdutoSearch), 'UTF-8');

        if ($this->itemProdutoSearch !== $term) {
            $this->itemProdutoSearch = $term;
        }

        if ($this->itemPendingProductId !== null) {
            $pendingNome = $this->pendingProductNome();

            if ($term === '' || ($pendingNome !== '' && $term === $pendingNome)) {
                $this->fecharProdutoLookup();

                return;
            }

            $this->limparPendenciaMantendoBusca();
        }

        if ($term === '') {
            $this->fecharProdutoLookup();
            $this->produtoResults = [];
            $this->produtoSelecionadoIndex = null;

            return;
        }

        $this->produtoLookupOpen = true;
        $this->atualizarProdutoResults();
    }

    public function updatedItemEntryQtd(): void
    {
        $this->recalcularPreviewInclusao();
    }

    public function updatedItemEntryPreco(): void
    {
        $this->recalcularPreviewInclusao();
    }

    public function abrirProdutoLookup(): void
    {
        $term = trim($this->itemProdutoSearch);

        if ($term === '') {
            return;
        }

        if ($this->itemPendingProductId !== null) {
            $pendingNome = $this->pendingProductNome();

            if ($pendingNome !== '' && mb_strtoupper($term, 'UTF-8') === $pendingNome) {
                return;
            }
        }

        $this->produtoLookupOpen = true;
        $this->atualizarProdutoResults();
    }

    public function fecharProdutoLookup(): void
    {
        $this->produtoLookupOpen = false;
    }

    public function confirmarInclusaoProduto(?string $termFromInput = null): void
    {
        if ($termFromInput !== null) {
            $fromInput = mb_strtoupper(trim($termFromInput), 'UTF-8');

            if ($fromInput !== '') {
                $this->itemProdutoSearch = $fromInput;
            }
        }

        $term = mb_strtoupper(trim($this->itemProdutoSearch), 'UTF-8');

        if ($term === '') {
            $this->formAlert = 'Informe o código, barras ou nome do produto.';
            $this->dispatch('erp-mov-saidas-focus-produto');

            return;
        }

        if ($this->itemPendingProductId !== null) {
            $pendingNome = $this->pendingProductNome();

            if ($pendingNome !== '' && $term === $pendingNome) {
                $this->dispatch('erp-mov-saidas-focus-qtd');

                return;
            }
        }

        $this->itemProdutoSearch = $term;

        $byCodigo = $this->encontrarProdutoPorCodigo($term);

        if ($byCodigo) {
            $this->prepararProdutoParaEntrada($byCodigo);

            return;
        }

        if ($this->termoPareceCodigoProduto($term)) {
            $this->produtoLookupOpen = true;
            $this->atualizarProdutoResults();
            $this->formAlert = 'Código não encontrado. Digite o nome para buscar por descrição.';
            $this->dispatch('erp-mov-saidas-focus-produto');

            return;
        }

        if ($this->produtoLookupOpen && $this->produtoResults !== []) {
            $index = $this->produtoSelecionadoIndex;

            if ($index === null || ! isset($this->produtoResults[$index])) {
                $index = 0;
            }

            $this->confirmarProdutoResultado((int) $index);

            return;
        }

        $this->produtoLookupOpen = true;
        $this->atualizarProdutoResults();

        if ($this->produtoResults === []) {
            $this->formAlert = 'Produto não encontrado. Verifique o código, barras ou nome.';
            $this->dispatch('erp-mov-saidas-focus-produto');

            return;
        }

        if (count($this->produtoResults) === 1) {
            $this->confirmarProdutoResultado(0);

            return;
        }

        $this->produtoSelecionadoIndex = 0;
        $this->formAlert = 'Selecione o produto na lista (↑ ↓ + Enter).';
        $this->dispatch('erp-mov-saidas-focus-produto');
    }

    public function focoInclusaoPrecoAposQtd(?string $qtdFromInput = null): void
    {
        if ($qtdFromInput !== null && trim($qtdFromInput) !== '') {
            $this->itemEntryQtd = trim($qtdFromInput);
        }

        if ($this->itemPendingProductId === null) {
            $this->confirmarInclusaoProduto();

            return;
        }

        $qtd = ErpMoney::tryParseBr($this->itemEntryQtd, 3);

        if ($qtd === null || $qtd <= 0) {
            $this->formAlert = 'Quantidade inválida.';
            $this->dispatch('erp-mov-saidas-focus-qtd');

            return;
        }

        $this->itemEntryQtd = ErpMoney::formatBr($qtd, 3);
        $this->recalcularPreviewInclusao();
        $this->dispatch('erp-mov-saidas-focus-preco');
    }

    public function confirmarInclusaoPreco(?string $precoFromInput = null): void
    {
        if ($precoFromInput !== null && trim($precoFromInput) !== '') {
            $this->itemEntryPreco = trim($precoFromInput);
        }

        if ($this->itemPendingProductId === null) {
            $this->confirmarInclusaoProduto();

            return;
        }

        $preco = ErpMoney::tryParseBr($this->itemEntryPreco, 4);

        if ($preco === null || $preco < 0) {
            $this->formAlert = 'Informe o preço de compra.';
            $this->dispatch('erp-mov-saidas-focus-preco');

            return;
        }

        $this->itemEntryPreco = ErpMoney::formatBr($preco, 2);
        $this->confirmarEntradaItemPendente();
    }

    public function selecionarProdutoInclusao(int $id): void
    {
        $product = Product::query()->where('ativo', true)->find($id);

        if (! $product) {
            $this->formAlert = 'Produto não encontrado.';

            return;
        }

        $this->prepararProdutoParaEntrada($product);
    }

    public function moverProdutoSelecionado(int $delta): void
    {
        if (! $this->produtoLookupOpen || $this->produtoResults === []) {
            return;
        }

        $index = ($this->produtoSelecionadoIndex ?? 0) + $delta;
        $count = count($this->produtoResults);
        $this->produtoSelecionadoIndex = max(0, min($count - 1, $index));
    }

    protected function confirmarProdutoResultado(int $index): void
    {
        if (! isset($this->produtoResults[$index])) {
            return;
        }

        $product = Product::query()->find($this->produtoResults[$index]['id']);

        if (! $product) {
            return;
        }

        $this->prepararProdutoParaEntrada($product);
    }

    protected function prepararProdutoParaEntrada(Product $product): void
    {
        $preco = (float) ($product->preco_compra ?: $product->preco_custo ?: 0);

        $this->itemPendingProductId = (int) $product->id;
        $this->itemProdutoSearch = mb_strtoupper((string) $product->descricao, 'UTF-8');
        $this->itemEntryPreco = ErpMoney::formatBr($preco, 2);
        $this->itemEntryQtd = ErpMoney::formatBr(1, 3);
        $this->recalcularPreviewInclusao();
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->produtoSelecionadoIndex = null;
        $this->formAlert = '';
        $this->dispatch('erp-mov-saidas-focus-qtd');
    }

    protected function confirmarEntradaItemPendente(): void
    {
        if ($this->itemPendingProductId === null) {
            return;
        }

        $product = Product::query()->find($this->itemPendingProductId);

        if (! $product) {
            $this->limparBarraInclusao();

            return;
        }

        $qtd = ErpMoney::parseBr($this->itemEntryQtd, 3);

        if ($qtd <= 0) {
            $this->formAlert = 'Informe a quantidade do item.';
            $this->dispatch('erp-mov-saidas-focus-qtd');

            return;
        }

        $preco = ErpMoney::parseBr($this->itemEntryPreco, 4);
        $total = round($qtd * $preco, 2);
        $productId = (int) $product->id;

        foreach ($this->itens as $index => $item) {
            if ((int) ($item['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $qtdAtual = $this->parseDecimalBr((string) ($item['qtd'] ?? '0'));
            $novaQtd = $qtdAtual + $qtd;
            $this->itens[$index]['qtd'] = ErpMoney::formatBr($novaQtd, 3);
            $this->itens[$index]['preco'] = ErpMoney::formatBr($preco, 2);
            $this->itens[$index]['total'] = ErpMoney::formatBr(round($novaQtd * $preco, 2), 2);
            $this->itemSelecionadoIndex = $index;
            $this->limparBarraInclusao();
            $this->dispatch('erp-mov-saidas-focus-produto');

            return;
        }

        $this->itens[] = [
            'product_id' => $productId,
            'codigo' => (string) ($product->codigo ?? ''),
            'descricao' => mb_strtoupper((string) $product->descricao, 'UTF-8'),
            'qtd' => ErpMoney::formatBr($qtd, 3),
            'preco' => ErpMoney::formatBr($preco, 2),
            'total' => ErpMoney::formatBr($total, 2),
        ];
        $this->itemSelecionadoIndex = count($this->itens) - 1;

        $this->limparBarraInclusao();
        $this->dispatch('erp-mov-saidas-focus-produto');
    }

    protected function atualizarProdutoResults(): void
    {
        $term = mb_strtoupper(trim($this->itemProdutoSearch), 'UTF-8');

        if ($term === '') {
            $this->produtoResults = [];
            $this->produtoSelecionadoIndex = null;

            return;
        }

        $buscaPorCodigo = $this->termoPareceCodigoProduto($term);
        $tokens = $buscaPorCodigo ? [$term] : $this->tokensBuscaProduto($term);
        $firstToken = $tokens[0] ?? $term;
        $reservas = app(EstoqueReservaService::class)->totaisReservadosAtivos(null);

        $produtos = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term, $buscaPorCodigo, $tokens): void {
                if ($buscaPorCodigo) {
                    $query->where('codigo', $term)
                        ->orWhere('referencia', $term)
                        ->orWhere('codigo_barras', $term)
                        ->orWhere('codigo_barras_caixa', $term);

                    return;
                }

                foreach ($tokens as $token) {
                    $like = '%'.$this->escapeLike($token).'%';
                    $query->where(function ($tokenQuery) use ($like): void {
                        $tokenQuery->where('codigo', 'like', $like)
                            ->orWhere('descricao', 'like', $like)
                            ->orWhere('referencia', 'like', $like)
                            ->orWhere('codigo_barras', 'like', $like)
                            ->orWhere('codigo_barras_caixa', 'like', $like);
                    });
                }
            })
            ->orderByRaw(
                'CASE WHEN codigo = ? THEN 0 WHEN codigo_barras = ? OR codigo_barras_caixa = ? OR referencia = ? THEN 1 WHEN descricao LIKE ? THEN 2 WHEN descricao LIKE ? THEN 3 ELSE 4 END',
                [$term, $term, $term, $term, $term.'%', $firstToken.'%'],
            )
            ->orderBy('descricao')
            ->limit(12)
            ->get(['id', 'codigo', 'descricao', 'preco_compra', 'preco_custo', 'estoque']);

        $this->produtoResults = $produtos
            ->map(function (Product $product) use ($reservas): array {
                $atual = (float) ($product->estoque ?? 0);
                $reservado = (float) ($reservas[$product->id] ?? 0);
                $preco = (float) ($product->preco_compra ?: $product->preco_custo ?: 0);
                $nome = mb_strtoupper((string) ($product->descricao ?? ''), 'UTF-8');

                return [
                    'id' => (int) $product->id,
                    'codigo' => mb_strtoupper((string) ($product->codigo ?? ''), 'UTF-8'),
                    'nome' => $nome,
                    'atual' => ErpMoney::formatBr($atual, 3),
                    'reservado' => ErpMoney::formatBr($reservado, 3),
                    'disponivel' => ErpMoney::formatBr($atual - $reservado, 3),
                    'preco' => ErpMoney::formatBr($preco, 2),
                ];
            })
            ->values()
            ->all();

        $this->produtoSelecionadoIndex = $this->produtoResults === [] ? null : 0;
    }

    protected function encontrarProdutoPorCodigo(string $codigo): ?Product
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo', $codigo)
                    ->orWhere('referencia', $codigo)
                    ->orWhere('codigo_barras', $codigo)
                    ->orWhere('codigo_barras_caixa', $codigo);

                if (ctype_digit($codigo)) {
                    $query->orWhereRaw('CAST(codigo AS CHAR) = ?', [$codigo]);
                }
            })
            ->first();
    }

    protected function pendingProductNome(): string
    {
        if ($this->itemPendingProductId === null) {
            return '';
        }

        $product = Product::query()->find($this->itemPendingProductId);

        return $product ? mb_strtoupper(trim((string) $product->descricao), 'UTF-8') : '';
    }

    protected function limparPendenciaMantendoBusca(): void
    {
        $this->itemPendingProductId = null;
        $this->itemEntryPreco = '';
        $this->itemEntryQtd = '1,000';
        $this->itemEntryTotalDisplay = '0,00';
    }

    protected function limparBarraInclusao(): void
    {
        $this->itemPendingProductId = null;
        $this->itemProdutoSearch = '';
        $this->itemEntryPreco = '';
        $this->itemEntryQtd = '1,000';
        $this->itemEntryTotalDisplay = '0,00';
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->produtoSelecionadoIndex = null;
        $this->formAlert = '';
    }

    protected function recalcularPreviewInclusao(): void
    {
        $qtd = ErpMoney::tryParseBr($this->itemEntryQtd, 3);
        $preco = ErpMoney::tryParseBr($this->itemEntryPreco, 4);
        $qtdCalc = ($qtd !== null && $qtd > 0) ? $qtd : 0.0;
        $precoCalc = ($preco !== null && $preco >= 0) ? $preco : 0.0;

        $this->itemEntryTotalDisplay = ErpMoney::formatBr(round($qtdCalc * $precoCalc, 2), 2);
    }

    /**
     * @return list<string>
     */
    protected function tokensBuscaProduto(string $term): array
    {
        $parts = preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $tokens[] = $part;
            }
        }

        return $tokens !== [] ? $tokens : [$term];
    }

    protected function termoPareceCodigoProduto(string $term): bool
    {
        $term = trim($term);

        return $term !== '' && ctype_digit($term);
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
