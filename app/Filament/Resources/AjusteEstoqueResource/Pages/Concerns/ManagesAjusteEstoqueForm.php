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

    public function createAjuste(): void
    {
        if ($this->showAjusteForm) {
            return;
        }

        $this->resetAjusteForm();
        $this->showAjusteForm = true;
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

        $ajuste = AjusteEstoque::query()->with('product')->find($recordId);

        if (! $ajuste || ! $ajuste->product) {
            Notification::make()->title('Ajuste não encontrado.')->warning()->send();

            return;
        }

        $product = $ajuste->product;
        $this->ajusteFormId = $ajuste->id;
        $this->ajusteForm = [
            'codigo_display' => (string) $ajuste->id,
            'data' => $ajuste->data->format('Y-m-d'),
            'product_id' => $product->id,
            'codigo_interno' => (string) $product->codigo,
            'codigo_barras' => (string) ($product->codigo_barras ?? ''),
            'referencia' => (string) ($product->referencia ?? ''),
            'descricao_busca' => $product->descricao,
            'estoque_atual' => $this->formatEstoqueBr((float) $product->estoque - (float) $ajuste->qtd_ajust),
            'quantidade' => $this->formatQtdAjustBr((float) $ajuste->qtd_ajust),
        ];
        $this->produtoSugestoes = [];
        $this->showAjusteForm = true;
    }

    public function closeAjusteForm(): void
    {
        $this->showAjusteForm = false;
        $this->ajusteFormId = null;
        $this->produtoSugestoes = [];
        $this->resetAjusteForm();
    }

    public function saveAjusteForm(): void
    {
        $productId = (int) ($this->ajusteForm['product_id'] ?? 0);
        $data = trim((string) ($this->ajusteForm['data'] ?? ''));
        $quantidade = BrDecimal::parse($this->ajusteForm['quantidade'] ?? 0, 3);

        if ($productId <= 0) {
            Notification::make()->title('Selecione um produto.')->warning()->send();

            return;
        }

        if ($data === '') {
            Notification::make()->title('Informe a data do ajuste.')->warning()->send();

            return;
        }

        if ($quantidade == 0.0) {
            Notification::make()->title('Informe a quantidade do ajuste.')->warning()->send();

            return;
        }

        try {
            $service = new AjusteEstoqueService();

            if ($this->ajusteFormId) {
                $ajuste = AjusteEstoque::query()->findOrFail($this->ajusteFormId);
                $service->atualizar($ajuste, $data, $quantidade);
                $message = 'Ajuste atualizado.';
            } else {
                $service->criar($productId, $data, $quantidade);
                $message = 'Ajuste gravado.';
            }

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
        if ($this->showAjusteForm) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $ajuste = AjusteEstoque::query()->find($recordId);

        if (! $ajuste) {
            return;
        }

        try {
            (new AjusteEstoqueService())->excluir($ajuste);
            $this->clearListSelection();
            $this->resetTable();
            Notification::make()->title('Ajuste excluído.')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Não foi possível excluir.')->body($e->getMessage())->warning()->send();
        }
    }

    public function updatedAjusteFormDescricaoBusca(): void
    {
        if ($this->ajusteFormId) {
            return;
        }

        $term = mb_strtoupper(trim((string) ($this->ajusteForm['descricao_busca'] ?? '')), 'UTF-8');

        if (mb_strlen($term) < 2) {
            $this->produtoSugestoes = [];

            return;
        }

        $like = '%' . $term . '%';

        $this->produtoSugestoes = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term, $like): void {
                $query->where('descricao', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('codigo_barras_caixa', 'like', $like);

                if (is_numeric($term)) {
                    $query->orWhere('codigo', (int) $term);
                }
            })
            ->orderBy('descricao')
            ->limit(12)
            ->get(['id', 'codigo', 'descricao', 'estoque', 'codigo_barras', 'referencia'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'codigo' => $product->codigo,
                'descricao' => $product->descricao,
                'estoque' => $this->formatEstoqueBr((float) $product->estoque),
                'codigo_barras' => $product->codigo_barras,
                'referencia' => $product->referencia,
            ])
            ->all();
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
        $this->produtoSugestoes = [];
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
