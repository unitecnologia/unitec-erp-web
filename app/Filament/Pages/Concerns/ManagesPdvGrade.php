<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Product;
use App\Models\ProductGrade;
use App\Support\Erp\ErpMoney;

trait ManagesPdvGrade
{
    /** @var array<int, array<string, mixed>> */
    public array $pdvGradeRows = [];

    public ?int $selectedPdvGradeIndex = null;

    public ?int $pdvPendingProductId = null;

    public ?float $pdvPendingQuantidade = null;

    public ?float $pdvPendingPreco = null;

    protected function loadPdvGradeRows(Product $product): void
    {
        $this->pdvGradeRows = $product->grades()
            ->orderBy('descricao')
            ->get()
            ->map(fn (ProductGrade $grade): array => [
                'grade_id' => $grade->id,
                'descricao' => mb_strtoupper($grade->descricao, 'UTF-8'),
                'tamanho' => $grade->tamanho ?? '',
                'qtd' => (float) $grade->qtd,
                'preco' => (float) $grade->preco,
            ])
            ->values()
            ->all();

        $this->selectedPdvGradeIndex = $this->pdvGradeRows === [] ? null : 0;
    }

    protected function openPdvGradeModal(Product $product, ?float $quantidade = null, ?float $preco = null): void
    {
        $this->pdvPendingProductId = $product->id;
        $this->pdvPendingQuantidade = $quantidade ?? $this->parsePdvLaunchQtd();
        $this->pdvPendingPreco = $preco ?? ErpMoney::parseBr($this->pdvLaunchPreco, 2);
        $this->loadPdvGradeRows($product);
        $this->openPdvModal('grade');
        $this->dispatch('erp-pdv-focus-grade');
    }

    public function selectPdvGradeRow(int $index): void
    {
        if (isset($this->pdvGradeRows[$index])) {
            $this->selectedPdvGradeIndex = $index;
        }
    }

    public function movePdvGradeSelection(int $delta): void
    {
        if ($this->pdvGradeRows === []) {
            return;
        }

        $count = count($this->pdvGradeRows);
        $index = ($this->selectedPdvGradeIndex ?? 0) + $delta;
        $this->selectedPdvGradeIndex = max(0, min($count - 1, $index));
    }

    public function confirmPdvGrade(): void
    {
        $product = $this->pdvPendingProductId
            ? Product::query()->find($this->pdvPendingProductId)
            : null;

        if (! $product) {
            $this->closePdvModal();
            $this->resetPdvPendingLaunch();

            return;
        }

        $index = $this->selectedPdvGradeIndex;
        $grade = ($index !== null && isset($this->pdvGradeRows[$index]))
            ? $this->pdvGradeRows[$index]
            : null;

        if (! $grade) {
            $this->notifyPdvError('Selecione uma grade.');

            return;
        }

        $quantidade = max(0.001, (float) ($this->pdvPendingQuantidade ?? 1));
        $preco = (float) ($grade['preco'] ?? 0);

        if ($preco <= 0) {
            $preco = $this->pdvPriceService()->resolvePrecoVenda($product, $quantidade);
        }

        $gradeId = (int) ($grade['grade_id'] ?? 0);
        $descricao = $product->descricao . ' - ' . ($grade['descricao'] ?? '');

        $this->closePdvModal();
        $this->resetPdvPendingLaunch(clearProduct: false);

        if ($product->usa_imei) {
            $this->openPdvSerialModal($product, $quantidade, $preco, $gradeId, $descricao);

            return;
        }

        $this->confirmAddProduct($product, $quantidade, $preco, $gradeId, null, $descricao);
    }

    public function cancelPdvGrade(): void
    {
        $this->closePdvModal();
        $this->resetPdvPendingLaunch();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function resetPdvPendingLaunch(bool $clearProduct = true): void
    {
        if ($clearProduct) {
            $this->pdvPendingProductId = null;
        }

        $this->pdvPendingQuantidade = null;
        $this->pdvPendingPreco = null;
        $this->pdvGradeRows = [];
        $this->selectedPdvGradeIndex = null;
    }
}
