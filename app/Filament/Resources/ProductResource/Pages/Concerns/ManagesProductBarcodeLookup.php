<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Support\Erp\BarcodeLookupService;
use Filament\Notifications\Notification;
use RuntimeException;

trait ManagesProductBarcodeLookup
{
    public function searchCodigoBarras(?string $codigoBarras = null): void
    {
        if (filled($codigoBarras)) {
            $this->data['codigo_barras'] = trim($codigoBarras);
        }

        if (blank($this->data['codigo_barras'] ?? null)) {
            $this->data['codigo_barras'] = $this->form->getState()['codigo_barras'] ?? null;
        }

        $barcode = preg_replace('/\D/', '', (string) ($this->data['codigo_barras'] ?? ''));

        if (strlen($barcode) < 8) {
            Notification::make()
                ->title('Informe um código de barras válido.')
                ->warning()
                ->send();

            return;
        }

        $excludeProductId = $this->isEditingProduct() ? $this->record?->getKey() : null;

        try {
            $fields = app(BarcodeLookupService::class)->fetch($barcode, $excludeProductId);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Consulta de código de barras')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->data['codigo_barras'] = $barcode;

        $source = (string) ($fields['source'] ?? 'upcitemdb');
        $existingProductId = $fields['existing_product_id'] ?? null;
        $fotoUrl = $fields['foto_url'] ?? null;
        unset($fields['foto_url'], $fields['source'], $fields['existing_product_id']);

        if (
            $source === 'internal'
            && $existingProductId
            && ! $this->isEditingProduct()
        ) {
            $existing = Product::query()->find($existingProductId);

            if ($existing) {
                $this->data['codigo_barras'] = $barcode;
                $this->form->fill($this->data);
                $this->openDuplicateConfirmModal($existing, 'codigo_barras');

                return;
            }
        }

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'peso_kg') {
                $this->data[$key] = $this->formatBrDecimal($value, 3);

                continue;
            }

            if ($key === 'preco_venda') {
                $this->data[$key] = $this->formatBrDecimal($value, 2);

                continue;
            }

            $this->data[$key] = $value;
        }

        $this->setPendingProductFotoFromUrl(is_string($fotoUrl) && $fotoUrl !== '' ? $fotoUrl : null);

        $this->form->fill($this->data);

        Notification::make()
            ->title('Produto encontrado')
            ->body(match ($source) {
                'internal' => 'Dados copiados de produto já cadastrado neste ERP.',
                'cosmos' => 'Dados e foto preenchidos via Cosmos (Bluesoft). Confira descrição e NCM antes de salvar.',
                'upcitemdb' => 'Dados preenchidos via consulta externa. Confira descrição e NCM antes de salvar.',
                'openfoodfacts' => 'Dados preenchidos via Open Food Facts. Confira descrição e NCM antes de salvar.',
                default => 'Dados preenchidos. Confira descrição e NCM antes de salvar.',
            })
            ->success()
            ->send();

        $this->dispatch('erp-masks-refresh');
    }
}
