<?php

namespace App\Filament\Resources\AjusteEstoqueResource\Pages\Concerns;

use App\Models\AjusteEstoque;
use App\Models\Product;
use App\Support\Erp\AjusteEstoqueService;
use App\Support\Erp\BrDecimal;
use Filament\Notifications\Notification;

trait ManagesAjusteEstoqueForm
{
    public bool $showAjusteForm = false;

    public ?int $ajusteFormId = null;

    /** @var array<string, mixed> */
    public array $ajusteForm = [];

    /** @var array<int, array<string, mixed>> */
    public array $produtoSugestoes = [];

    public int $selectedProdutoSugestaoIndex = 0;

    public function createAjuste(): void
    {
        if ($this->showAjusteForm) {
            return;
        }

        $this->resetAjusteForm();
        $this->showAjusteForm = true;
        $this->dispatch('erp-ajuste-focus-codigo-interno');
    }

    public function editAjuste(): void
    {
        if ($this->showAjusteForm) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        // Ajuste já gravado movimenta estoque na hora — não reabre para alteração.
        Notification::make()
            ->title('Ajuste já finalizado.')
            ->body('Não é possível alterar um ajuste após a gravação. Se necessário, exclua e lance um novo.')
            ->warning()
            ->send();
    }

    public function closeAjusteForm(): void
    {
        $this->showAjusteForm = false;
        $this->ajusteFormId = null;
        $this->fecharSugestoesProduto();
        $this->resetAjusteForm();
    }

    public function handleAjusteEscape(): void
    {
        if ($this->produtoSugestoes !== []) {
            $this->fecharSugestoesProduto();

            return;
        }

        $this->closeAjusteForm();
    }

    public function saveAjusteForm(): void
    {
        $productId = (int) ($this->ajusteForm['product_id'] ?? 0);
        $data = trim((string) ($this->ajusteForm['data'] ?? ''));
        $quantidade = BrDecimal::parse($this->ajusteForm['quantidade'] ?? 0, 3);
        $modo = (string) ($this->ajusteForm['modo'] ?? 'somar');
        if (! in_array($modo, ['somar', 'substituir'], true)) {
            $modo = 'somar';
        }

        if ($productId <= 0) {
            Notification::make()->title('Selecione um produto.')->warning()->send();

            return;
        }

        if ($data === '') {
            Notification::make()->title('Informe a data do ajuste.')->warning()->send();

            return;
        }

        if ($modo === 'somar' && $quantidade == 0.0) {
            Notification::make()->title('Informe a quantidade do ajuste.')->warning()->send();

            return;
        }

        try {
            if ($this->ajusteFormId) {
                Notification::make()
                    ->title('Ajuste já finalizado.')
                    ->body('Não é possível alterar um ajuste após a gravação.')
                    ->warning()
                    ->send();

                return;
            }

            $service = new AjusteEstoqueService();
            $service->criar($productId, $data, $quantidade, $modo);
            $message = 'Ajuste gravado.';

            $this->closeAjusteForm();
            $this->clearListSelection();
            $this->resetTable();

            Notification::make()->title($message)->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Não foi possível gravar.')->body($e->getMessage())->warning()->send();
        }
    }

    public function deleteAjuste(): void
    {
        Notification::make()
            ->title('Exclusão não permitida.')
            ->body('Ajuste finalizado não pode ser excluído.')
            ->warning()
            ->send();
    }

    public function updatedAjusteFormDescricaoBusca(): void
    {
        if ($this->ajusteFormId) {
            return;
        }

        $raw = (string) ($this->ajusteForm['descricao_busca'] ?? '');
        $term = mb_strtoupper(trim($raw), 'UTF-8');

        if ($term !== $raw) {
            $this->ajusteForm['descricao_busca'] = $term;
        }

        $this->carregarSugestoesProduto($term);
    }

    public function moverSugestaoProduto(int $delta): void
    {
        // Navegação ↑↓ é feita no cliente (Alpine) para ficar imediata.
    }

    public function confirmarProdutoSugestao(): void
    {
        if ($this->ajusteFormId) {
            return;
        }

        if ($this->produtoSugestoes === []) {
            $term = mb_strtoupper(trim((string) ($this->ajusteForm['descricao_busca'] ?? '')), 'UTF-8');
            $this->carregarSugestoesProduto($term);
        }

        if ($this->produtoSugestoes === []) {
            return;
        }

        $index = $this->selectedProdutoSugestaoIndex;

        if (! isset($this->produtoSugestoes[$index])) {
            $index = 0;
        }

        $this->selecionarProdutoSugestao((int) $this->produtoSugestoes[$index]['id']);
    }

    public function fecharSugestoesProduto(): void
    {
        $this->produtoSugestoes = [];
        $this->selectedProdutoSugestaoIndex = 0;
    }

    /**
     * Ranking: código exato → descrição começa com o termo → palavra no meio → contém → demais.
     */
    private function carregarSugestoesProduto(string $term): void
    {
        if (mb_strlen($term) < 2) {
            $this->fecharSugestoesProduto();

            return;
        }

        $like = '%'.$term.'%';
        $prefix = $term.'%';
        $word = '% '.$term.'%';
        $wordEnd = '% '.$term;
        $somenteDigitos = ctype_digit($term);

        $this->produtoSugestoes = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term, $like, $somenteDigitos): void {
                $query->where('descricao', 'like', $like)
                    ->orWhere('referencia', 'like', $like);

                if ($somenteDigitos) {
                    $query->orWhere('codigo', $term)
                        ->orWhereRaw('CAST(codigo AS CHAR) = ?', [$term])
                        ->orWhere('codigo', 'like', $like)
                        ->orWhere('codigo_barras', $term)
                        ->orWhere('codigo_barras', 'like', $like)
                        ->orWhere('codigo_barras_caixa', $term);
                }
            })
            ->orderByRaw(
                'CASE
                    WHEN codigo = ? OR CAST(codigo AS CHAR) = ? THEN 0
                    WHEN descricao LIKE ? THEN 1
                    WHEN descricao LIKE ? OR descricao LIKE ? THEN 2
                    WHEN descricao LIKE ? THEN 3
                    WHEN referencia LIKE ? THEN 4
                    ELSE 5
                END',
                [$term, $term, $prefix, $word, $wordEnd, $like, $prefix]
            )
            ->orderByRaw('CASE WHEN LOCATE(?, descricao) = 0 THEN 9999 ELSE LOCATE(?, descricao) END', [$term, $term])
            ->orderBy('descricao')
            ->limit(12)
            ->get(['id', 'codigo', 'descricao', 'estoque', 'codigo_barras'])
            ->map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'codigo' => (string) $product->codigo,
                'descricao' => (string) $product->descricao,
                'estoque' => number_format((float) $product->estoque, 3, ',', '.'),
                'codigo_barras' => (string) ($product->codigo_barras ?? ''),
            ])
            ->all();

        $this->selectedProdutoSugestaoIndex = 0;
        $this->dispatch('erp-ajuste-sugestoes-ready');
    }

    public function resolveProdutoCodigoInterno(): void
    {
        if ($this->ajusteFormId) {
            return;
        }

        $codigo = mb_strtoupper(trim((string) ($this->ajusteForm['codigo_interno'] ?? '')), 'UTF-8');

        if ($codigo === '') {
            return;
        }

        $product = $this->findProductByCodigo($codigo);
        $this->aplicarProdutoNoForm($product, 'Código interno não encontrado.');
    }

    public function resolveProdutoCodigoBarras(): void
    {
        if ($this->ajusteFormId) {
            return;
        }

        $codigo = trim((string) ($this->ajusteForm['codigo_barras'] ?? ''));

        if ($codigo === '') {
            return;
        }

        $product = $this->findProductByBarras($codigo);
        $this->aplicarProdutoNoForm($product, 'Código de barras não encontrado.');
    }

    public function resolveProdutoReferencia(): void
    {
        if ($this->ajusteFormId) {
            return;
        }

        $referencia = mb_strtoupper(trim((string) ($this->ajusteForm['referencia'] ?? '')), 'UTF-8');

        if ($referencia === '') {
            return;
        }

        $product = Product::query()
            ->where('ativo', true)
            ->where('referencia', $referencia)
            ->first();

        $this->aplicarProdutoNoForm($product, 'Referência não encontrada.');
    }

    public function selecionarProdutoSugestao(int $productId): void
    {
        if ($this->ajusteFormId) {
            return;
        }

        $product = Product::query()->where('ativo', true)->find($productId);
        $this->aplicarProdutoNoForm($product, 'Produto não encontrado.');
    }

    protected function resetAjusteForm(): void
    {
        $this->ajusteForm = [
            'codigo_display' => (string) (new AjusteEstoqueService())->proximoCodigoExibicao(),
            'data' => now()->format('Y-m-d'),
            'product_id' => null,
            'codigo_interno' => '',
            'codigo_barras' => '',
            'referencia' => '',
            'descricao_busca' => '',
            'estoque_atual' => '',
            'quantidade' => '0',
            'modo' => 'somar',
        ];
    }

    protected function aplicarProdutoNoForm(?Product $product, string $erro): void
    {
        if (! $product) {
            Notification::make()->title($erro)->warning()->send();

            return;
        }

        $this->ajusteForm['product_id'] = $product->id;
        $this->ajusteForm['codigo_interno'] = (string) $product->codigo;
        $this->ajusteForm['codigo_barras'] = (string) ($product->codigo_barras ?? '');
        $this->ajusteForm['referencia'] = (string) ($product->referencia ?? '');
        $this->ajusteForm['descricao_busca'] = $product->descricao;
        $this->ajusteForm['estoque_atual'] = $this->formatEstoqueBr((float) $product->estoque);
        $this->fecharSugestoesProduto();
        $this->dispatch('erp-ajuste-focus-qtd');
    }

    protected function findProductByCodigo(string $codigo): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo', $codigo);

                if (is_numeric($codigo)) {
                    $query->orWhere('codigo', (int) $codigo);
                }
            })
            ->first();
    }

    protected function findProductByBarras(string $codigo): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo_barras', $codigo)
                    ->orWhere('codigo_barras_caixa', $codigo);
            })
            ->first();
    }

    protected function formatEstoqueBr(float $value): string
    {
        return number_format($value, 3, ',', '.');
    }

    protected function formatQtdAjustBr(float $value): string
    {
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : 3;

        return number_format($value, $decimals, ',', '.');
    }
}
