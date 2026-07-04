<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Product;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Nfe\NfeCalculoService;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait ManagesNfeItemGrid
{
    public string $nfeItemCodigoInput = '';

    public string $nfeItemProdutoSearch = '';

    public ?int $nfeItemPendingProductId = null;

    public string $nfeItemEntryCfop = '';

    public string $nfeItemEntryCst = '';

    public string $nfeItemEntryPreco = '';

    public string $nfeItemEntryQtd = '1,0000';

    public string $nfeItemEntryUnidade = 'UN';

    public string $nfeItemEntryTotalDisplay = '';

    public bool $nfeProdutoLookupOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $nfeProdutoResults = [];

    public ?int $nfeSelectedProdutoIndex = null;

    public ?string $nfeProdutoPreviewFotoUrl = null;

    public function selectNfeRow(int $index): void
    {
        if ($index >= 0 && $index < count($this->nfeModalRows)) {
            $this->nfeSelectedRowIndex = $index;
        }
    }

    public function handleNfeItemCodigoEnter(): void
    {
        if (blank(trim($this->nfeItemCodigoInput))) {
            $this->dispatch('erp-nfe-focus-item-produto');

            return;
        }

        $this->submitNfeItemByCodigo();
    }

    public function handleNfeItemProdutoEnter(): void
    {
        if ($this->nfeProdutoLookupOpen && $this->nfeProdutoResults !== []) {
            if (count($this->nfeProdutoResults) === 1) {
                $this->selectNfeProdutoResult(0, advanceToCfop: true);

                return;
            }

            if ($this->nfeSelectedProdutoIndex !== null && isset($this->nfeProdutoResults[$this->nfeSelectedProdutoIndex])) {
                $this->confirmNfeProdutoSelection(advanceToCfop: true);

                return;
            }

            return;
        }

        if ($this->nfeItemPendingProductId === null) {
            $this->submitNfeItemProdutoSearch(advanceToCfop: true);

            return;
        }

        $this->closeNfeProdutoLookup();
        $this->dispatch('erp-nfe-focus-item-cfop');
    }

    public function advanceNfeEntryField(string $from): void
    {
        if ($this->nfeItemPendingProductId === null) {
            return;
        }

        match ($from) {
            'cfop' => $this->dispatch('erp-nfe-focus-item-cst'),
            'cst' => $this->dispatch('erp-nfe-focus-item-preco'),
            'preco' => $this->dispatch('erp-nfe-focus-item-quantidade'),
            'qtd' => $this->dispatch('erp-nfe-focus-item-unidade'),
            'unid' => $this->confirmPendingNfeItemEntry(),
            default => null,
        };
    }

    public function submitNfeItemByCodigo(): void
    {
        $codigo = mb_strtoupper(trim($this->nfeItemCodigoInput), 'UTF-8');

        if ($codigo === '') {
            return;
        }

        $product = $this->findNfeProductByCodigo($codigo);

        if (! $product) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Verifique o código informado.')
                ->warning()
                ->send();
            $this->dispatch('erp-nfe-focus-item-codigo');

            return;
        }

        $this->stageNfeProductForEntry($product);
    }

    public function updatedNfeItemProdutoSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->nfeItemProdutoSearch !== $upper) {
            $this->nfeItemProdutoSearch = $upper;
        }

        $this->prepareNfeProdutoSearch();
        $this->nfeProdutoLookupOpen = true;
        $this->refreshNfeProdutoResults();
    }

    public function openNfeProdutoLookup(): void
    {
        $this->nfeProdutoLookupOpen = true;

        if (filled(trim($this->nfeItemProdutoSearch))) {
            $this->prepareNfeProdutoSearch();
            $this->refreshNfeProdutoResults();
        }
    }

    protected function prepareNfeProdutoSearch(): void
    {
        if ($this->nfeItemPendingProductId === null) {
            return;
        }

        $this->nfeItemPendingProductId = null;
        $this->nfeItemCodigoInput = '';
        $this->nfeItemEntryCfop = '';
        $this->nfeItemEntryCst = '';
        $this->nfeItemEntryPreco = '';
        $this->nfeItemEntryQtd = '1,0000';
        $this->nfeItemEntryUnidade = 'UN';
        $this->nfeItemEntryTotalDisplay = '';
    }

    public function refreshNfeProdutoResults(): void
    {
        $term = trim($this->nfeItemProdutoSearch);

        if ($term === '') {
            $this->nfeProdutoResults = [];
            $this->nfeSelectedProdutoIndex = null;
            $this->clearNfeProdutoPreviewFoto();

            return;
        }

        $like = '%' . $term . '%';

        $this->nfeProdutoResults = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($like, $term): void {
                $query->where('codigo', 'like', $like)
                    ->orWhere('descricao', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('codigo_barras_caixa', 'like', $like);

                if (ctype_digit($term)) {
                    $query->orWhere('codigo', $term);
                }
            })
            ->orderBy('descricao')
            ->limit(50)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'codigo' => mb_strtoupper((string) $product->codigo, 'UTF-8'),
                'descricao' => mb_strtoupper($product->descricao, 'UTF-8'),
            ])
            ->all();

        $this->nfeSelectedProdutoIndex = $this->nfeProdutoResults === [] ? null : 0;
        $this->syncNfeProdutoPreviewFotoFromSelection();
    }

    public function moveNfeProdutoSelection(int $delta): void
    {
        if (! $this->nfeProdutoLookupOpen || $this->nfeProdutoResults === []) {
            return;
        }

        $index = ($this->nfeSelectedProdutoIndex ?? 0) + $delta;
        $count = count($this->nfeProdutoResults);
        $this->nfeSelectedProdutoIndex = max(0, min($count - 1, $index));
        $this->syncNfeProdutoPreviewFotoFromSelection();
        $this->dispatch('erp-nfe-scroll-produto-selection');
    }

    public function selectNfeProdutoResult(int $index, bool $advanceToCfop = false): void
    {
        if (! isset($this->nfeProdutoResults[$index])) {
            return;
        }

        $this->nfeSelectedProdutoIndex = $index;
        $this->syncNfeProdutoPreviewFotoFromSelection();
        $this->confirmNfeProdutoSelection(advanceToCfop: $advanceToCfop);
    }

    public function confirmNfeProdutoSelection(bool $advanceToCfop = false): void
    {
        $index = $this->nfeSelectedProdutoIndex;

        if ($index === null || ! isset($this->nfeProdutoResults[$index])) {
            return;
        }

        $product = Product::query()->find($this->nfeProdutoResults[$index]['id']);

        if (! $product) {
            return;
        }

        $this->stageNfeProductForEntry($product, advanceToCfop: $advanceToCfop);
    }

    public function submitNfeItemProdutoSearch(bool $advanceToCfop = false): void
    {
        $term = trim($this->nfeItemProdutoSearch);

        if ($term === '') {
            return;
        }

        $this->refreshNfeProdutoResults();

        if ($this->nfeProdutoResults === []) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Verifique o código ou a descrição informada.')
                ->warning()
                ->send();
            $this->dispatch('erp-nfe-focus-item-produto');

            return;
        }

        if (count($this->nfeProdutoResults) === 1) {
            $this->selectNfeProdutoResult(0, advanceToCfop: $advanceToCfop);

            return;
        }

        if ($this->nfeSelectedProdutoIndex !== null && isset($this->nfeProdutoResults[$this->nfeSelectedProdutoIndex])) {
            $this->confirmNfeProdutoSelection(advanceToCfop: $advanceToCfop);

            return;
        }

        $this->nfeProdutoLookupOpen = true;
        $this->nfeSelectedProdutoIndex = 0;
        $this->dispatch('erp-nfe-focus-item-produto');
    }

    public function closeNfeProdutoLookup(): void
    {
        $this->nfeProdutoLookupOpen = false;
        $this->clearNfeProdutoPreviewFoto();
    }

    public function updatedNfeItemEntryQtd(): void
    {
        $this->recalcNfeEntryRowPreview();
    }

    public function updatedNfeItemEntryPreco(): void
    {
        $this->recalcNfeEntryRowPreview();
    }

    public function confirmPendingNfeItemEntry(): void
    {
        if ($this->nfeItemPendingProductId === null) {
            return;
        }

        $product = Product::query()->find($this->nfeItemPendingProductId);

        if (! $product) {
            $this->clearNfeItemEntryRow();

            return;
        }

        $qtd = ErpMoney::parseBr($this->nfeItemEntryQtd, 4);

        if ($qtd <= 0) {
            Notification::make()->title('Informe a quantidade do item.')->warning()->send();
            $this->dispatch('erp-nfe-focus-item-quantidade');

            return;
        }

        $this->nfeModalRows[] = [
            'key' => 'new-' . Str::uuid()->toString(),
            'product_id' => $product->id,
            'codigo' => (string) $product->codigo,
            'descricao' => mb_strtoupper(trim($this->nfeItemProdutoSearch), 'UTF-8'),
            'cfop' => trim($this->nfeItemEntryCfop),
            'cst' => trim($this->nfeItemEntryCst),
            'quantidade' => ErpMoney::formatBr($qtd, 4),
            'valor_unitario' => ErpMoney::formatBr(ErpMoney::parseBr($this->nfeItemEntryPreco, 4), 4),
            'unidade' => mb_strtoupper(trim($this->nfeItemEntryUnidade) ?: 'UN', 'UTF-8'),
            'desconto' => 0.0,
        ];

        $this->nfeSelectedRowIndex = count($this->nfeModalRows) - 1;
        $this->clearNfeItemEntryRow();
        $this->recalculateNfeTotais();
        $this->dispatch('erp-nfe-focus-item-codigo');
    }

    public function resolveNfeItemProductFromCodigo(int $index): void
    {
        if (! isset($this->nfeModalRows[$index])) {
            return;
        }

        $codigo = mb_strtoupper(trim((string) ($this->nfeModalRows[$index]['codigo'] ?? '')), 'UTF-8');

        if ($codigo === '') {
            return;
        }

        $product = $this->findNfeProductByCodigo($codigo);

        if (! $product) {
            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        $qtd = ErpMoney::parseBr($this->nfeModalRows[$index]['quantidade'] ?? '1', 4);
        $this->applyProductToNfeRow($index, $product, max(0.0001, $qtd));
        $this->recalculateNfeTotais();
    }

    public function updatedNfeModalRows(): void
    {
        foreach (array_keys($this->nfeModalRows) as $index) {
            if (isset($this->nfeModalRows[$index]['descricao'])) {
                $this->nfeModalRows[$index]['descricao'] = mb_strtoupper(
                    trim((string) $this->nfeModalRows[$index]['descricao']),
                    'UTF-8',
                );
            }

            if (isset($this->nfeModalRows[$index]['unidade'])) {
                $this->nfeModalRows[$index]['unidade'] = mb_strtoupper(
                    trim((string) $this->nfeModalRows[$index]['unidade']) ?: 'UN',
                    'UTF-8',
                );
            }
        }

        $this->recalculateNfeTotais();
    }

    public function deleteNfeSelectedItem(): void
    {
        if ($this->nfeModalRows === []) {
            return;
        }

        $index = min($this->nfeSelectedRowIndex, count($this->nfeModalRows) - 1);
        array_splice($this->nfeModalRows, $index, 1);
        $this->nfeSelectedRowIndex = max(0, $index - 1);

        if ($this->nfeModalRows === []) {
            $this->nfeSelectedRowIndex = 0;
        }

        $this->recalculateNfeTotais();
    }

    protected function stageNfeProductForEntry(Product $product, bool $advanceToCfop = false): void
    {
        $preview = $this->previewNfeProductRow($product, 1.0);

        $this->nfeItemPendingProductId = $product->id;
        $this->nfeItemCodigoInput = (string) $product->codigo;
        $this->nfeItemProdutoSearch = mb_strtoupper($product->descricao, 'UTF-8');
        $this->nfeItemEntryCfop = (string) ($preview['cfop'] ?? '');
        $this->nfeItemEntryCst = (string) (($preview['cst'] ?? '') ?: ($preview['csosn'] ?? ''));
        $this->nfeItemEntryPreco = ErpMoney::formatBr((float) ($preview['valor_unitario'] ?? $product->preco_venda), 4);
        $this->nfeItemEntryQtd = ErpMoney::formatBr(1, 4);
        $this->nfeItemEntryUnidade = mb_strtoupper((string) ($preview['unidade'] ?? $product->unidade ?: 'UN'), 'UTF-8');
        $this->recalcNfeEntryRowPreview();
        $this->nfeProdutoLookupOpen = false;
        $this->nfeProdutoResults = [];
        $this->nfeSelectedProdutoIndex = null;
        $this->clearNfeProdutoPreviewFoto();
        $this->dispatch($advanceToCfop ? 'erp-nfe-focus-item-cfop' : 'erp-nfe-focus-item-produto');
    }

    protected function applyProductToNfeRow(int $index, Product $product, float $qtd): void
    {
        $preview = $this->previewNfeProductRow($product, $qtd);

        $this->nfeModalRows[$index]['product_id'] = $product->id;
        $this->nfeModalRows[$index]['codigo'] = (string) $product->codigo;
        $this->nfeModalRows[$index]['descricao'] = mb_strtoupper($product->descricao, 'UTF-8');
        $this->nfeModalRows[$index]['cfop'] = (string) ($preview['cfop'] ?? '');
        $this->nfeModalRows[$index]['cst'] = (string) (($preview['cst'] ?? '') ?: ($preview['csosn'] ?? ''));
        $this->nfeModalRows[$index]['quantidade'] = ErpMoney::formatBr($qtd, 4);
        $this->nfeModalRows[$index]['valor_unitario'] = ErpMoney::formatBr((float) ($preview['valor_unitario'] ?? $product->preco_venda), 4);
        $this->nfeModalRows[$index]['unidade'] = mb_strtoupper((string) ($preview['unidade'] ?? $product->unidade ?: 'UN'), 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    protected function previewNfeProductRow(Product $product, float $qtd): array
    {
        $empresaId = $this->resolveEmpresaId();
        $calculated = app(NfeCalculoService::class)->calcular(
            [[
                'product_id' => $product->id,
                'descricao' => $product->descricao,
                'quantidade' => $qtd,
                'valor_unitario' => (float) $product->preco_venda,
                'desconto' => 0.0,
            ]],
            $empresaId ? Empresa::query()->find($empresaId) : null,
            $this->nfeForm['uf'] ?? null,
        );

        return $calculated['rows'][0] ?? [];
    }

    protected function recalcNfeEntryRowPreview(): void
    {
        $qtd = ErpMoney::parseBr($this->nfeItemEntryQtd, 4);
        $preco = ErpMoney::parseBr($this->nfeItemEntryPreco, 4);

        if ($qtd <= 0) {
            $qtd = 1;
        }

        if ($preco < 0) {
            $preco = 0;
        }

        $this->nfeItemEntryQtd = ErpMoney::formatBr($qtd, 4);
        $this->nfeItemEntryPreco = ErpMoney::formatBr($preco, 4);
        $this->nfeItemEntryTotalDisplay = ErpMoney::formatBr(round($qtd * $preco, 2), 2);
    }

    protected function clearNfeItemEntryRow(): void
    {
        $this->nfeItemPendingProductId = null;
        $this->nfeItemCodigoInput = '';
        $this->nfeItemProdutoSearch = '';
        $this->nfeItemEntryCfop = '';
        $this->nfeItemEntryCst = '';
        $this->nfeItemEntryPreco = '';
        $this->nfeItemEntryQtd = '1,0000';
        $this->nfeItemEntryUnidade = 'UN';
        $this->nfeItemEntryTotalDisplay = '';
        $this->nfeProdutoLookupOpen = false;
        $this->nfeProdutoResults = [];
        $this->nfeSelectedProdutoIndex = null;
        $this->clearNfeProdutoPreviewFoto();
    }

    protected function syncNfeProdutoPreviewFotoFromSelection(): void
    {
        $index = $this->nfeSelectedProdutoIndex;

        if ($index === null || ! isset($this->nfeProdutoResults[$index])) {
            $this->clearNfeProdutoPreviewFoto();

            return;
        }

        $productId = (int) $this->nfeProdutoResults[$index]['id'];
        $this->nfeProdutoPreviewFotoUrl = Product::query()->find($productId)?->fotoUrl();
    }

    protected function clearNfeProdutoPreviewFoto(): void
    {
        $this->nfeProdutoPreviewFotoUrl = null;
    }

    protected function findNfeProductByCodigo(string $codigo): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo', $codigo)
                    ->orWhere('referencia', $codigo)
                    ->orWhere('codigo_barras', $codigo)
                    ->orWhere('codigo_barras_caixa', $codigo);
            })
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function formatNfeModalRowsForDisplay(array $rows): array
    {
        $money2 = [
            'total', 'desconto', 'frete', 'seguro', 'outros',
            'base_icms', 'valor_icms', 'base_desoneracao', 'desc_desoneracao', 'valor_desoneracao',
            'base_ipi', 'valor_ipi', 'base_pis_icms', 'valor_pis_icms', 'base_cofins_icms', 'valor_cofins_icms',
            'v_ibs_mun', 'v_ibs_uf', 'v_cbs', 'bc_ibs',
        ];
        $money4 = [
            'quantidade', 'valor_unitario', 'aliq_icms', 'aliq_ipi', 'aliq_pis_icms', 'aliq_cofins_icms',
            'alq_cbs', 'alq_ibs_mun', 'alq_ibs_uf',
        ];

        return array_map(function (array $row) use ($money2, $money4): array {
            foreach ($money2 as $field) {
                $row[$field] = ErpMoney::formatBr((float) ($row[$field] ?? 0), 2);
            }

            foreach ($money4 as $field) {
                $row[$field] = ErpMoney::formatBr((float) ($row[$field] ?? 0), 4);
            }

            $row['cst'] = (string) (($row['cst'] ?? '') ?: ($row['csosn'] ?? ''));
            $row['info_adicionais'] = (string) ($row['info_adicionais'] ?? '');
            $row['motivo_desoneracao'] = (string) ($row['motivo_desoneracao'] ?? '');
            $row['class_trib'] = (string) ($row['class_trib'] ?? '');
            $row['cst_ibs_cbs'] = (string) ($row['cst_ibs_cbs'] ?? '');

            return $row;
        }, $rows);
    }
}
