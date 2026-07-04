<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Support\Erp\ProductFormValidator;
use App\Support\Erp\ProductPriceCalculator;
use App\Support\Erp\BrDecimal;

trait ManagesProductPriceCalculation
{
    protected bool $suppressProductPriceRecalculation = false;

    public function updatedDataPrecoCompra(): void
    {
        if ($this->suppressProductPriceRecalculation) {
            return;
        }

        $this->recalculateProductPricesFromCompra();
    }

    public function updatedDataPctCustos(): void
    {
        if ($this->suppressProductPriceRecalculation) {
            return;
        }

        $this->recalculateProductPricesFromCompra();
    }

    public function updatedDataPrecoCusto(): void
    {
        if ($this->suppressProductPriceRecalculation) {
            return;
        }

        $this->recalculateProductPricesFromCusto();
    }

    public function updatedDataPctLucro(): void
    {
        if ($this->suppressProductPriceRecalculation) {
            return;
        }

        $this->recalculateProductPricesFromMargem();
    }

    public function updatedDataPrecoVenda(): void
    {
        if ($this->suppressProductPriceRecalculation) {
            return;
        }

        $this->recalculateProductPricesFromVenda();
    }

    public function recalculateProductPricesFromCompra(): void
    {
        $this->applyPriceCalculation(ProductPriceCalculator::recalculateFromCompra($this->data ?? []));
    }

    public function recalculateProductPricesFromCusto(): void
    {
        $this->applyPriceCalculation(ProductPriceCalculator::recalculateFromCusto($this->data ?? []));
    }

    public function recalculateProductPricesFromMargem(): void
    {
        $this->applyPriceCalculation(ProductPriceCalculator::recalculateFromMargem($this->data ?? []));
    }

    public function recalculateProductPricesFromVenda(): void
    {
        $this->applyPriceCalculation(ProductPriceCalculator::recalculateFromVenda($this->data ?? []));
    }

    public function recalculateProductPricesBeforeSave(): void
    {
        if (! is_array($this->data)) {
            return;
        }

        $compra = BrDecimal::parse($this->data['preco_compra'] ?? 0, 2);

        if ($compra > 0) {
            $this->data = ProductPriceCalculator::recalculateFromCompra($this->data);
        } else {
            $custo = BrDecimal::parse($this->data['preco_custo'] ?? 0, 2);

            if ($custo > 0) {
                $this->data = ProductPriceCalculator::recalculateFromMargem($this->data);
            }
        }

        $this->data = $this->formatProductFormDataForDisplay($this->data);
    }

    public function normalizeProductBarcodeOnBlur(): void
    {
        $this->fillProductBarcodeIfEmpty('codigo_barras');
    }

    public function normalizeProductBarcodeCaixaOnBlur(): void
    {
        $this->fillProductBarcodeIfEmpty('codigo_barras_caixa');
    }

    protected function fillProductBarcodeIfEmpty(string $field): void
    {
        $barcode = trim((string) ($this->data[$field] ?? ''));

        if ($barcode !== '') {
            return;
        }

        $codigo = (int) preg_replace('/\D/', '', (string) ($this->data['codigo'] ?? '0'));

        if ($codigo <= 0) {
            return;
        }

        $this->data[$field] = \App\Support\Erp\ProductEanGenerator::generate($codigo);
        $this->form->fill($this->data);
    }

    public function syncEstoqueFromInicialOnBlur(): void
    {
        if ($this->isEditingProduct()) {
            return;
        }

        $inicial = $this->parseBrDecimal($this->data['estoque_inicial'] ?? 0, 0);
        $this->data['estoque'] = $this->formatBrDecimal($inicial, 3);
        $this->form->fill($this->data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyPriceCalculation(array $data): void
    {
        $this->suppressProductPriceRecalculation = true;

        try {
            $formatted = $this->formatProductFormDataForDisplay($data);
            $this->data = $formatted;
            $this->form->fill($formatted);
            $this->dispatch('erp-masks-refresh');
        } finally {
            $this->suppressProductPriceRecalculation = false;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validateAndNormalizeProductBeforeSave(array $data): array
    {
        $excludeId = $this->isEditingProduct() ? $this->record?->getKey() : null;

        ProductFormValidator::validateBeforeSave($data, $excludeId);

        if (($data['is_grade'] ?? false) && ($data['contr_est_grade'] ?? false)) {
            ProductFormValidator::validateGradeStock(
                $data,
                $this->gradeRowsTotalQty(),
                (bool) $this->currentEmpresa()?->param_geral_bloquear_estoque_negativo,
            );
        }

        return ProductFormValidator::normalizeBarcodeForSave($data);
    }
}
