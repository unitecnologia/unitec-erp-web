<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ErpProductFormPage;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use ErpProductFormPage;

    protected static string $resource = ProductResource::class;

    public function mount(int | string $record): void
    {
        if (request()->boolean('pdv')) {
            $this->embedsInPdv = true;
        }

        parent::mount($record);

        ErpScreen::set('Cadastro de Produtos');
        $this->ensureProductFormEmpresaId();

        $this->syncProductFormData();
        $this->applyEmpresaPrecosToFormData($this->record);
        $this->hydrateNcmDescricaoFromCatalog(fillForm: false);
        $this->form->fill($this->data);
        $this->mountProductPhoto();
        $this->loadProductGrades($this->record);
        $this->loadProductCompositions($this->record);
        $this->loadProductPriceTableItems($this->record);
        $this->loadProductPriceHistories($this->record);
        $this->loadProductImeis($this->record);
        $this->loadProductReservas($this->record);
        $this->loadProductLotes($this->record);
        $this->captureProductFormBaseline();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->erpFormReturnRedirectUrl($this->getProductListRedirectUrl());
    }

    protected function afterSave(): void
    {
        $this->syncProductChildRecords($this->record);
        $this->syncProductFormData();

        if ($this->record) {
            app(\App\Support\Erp\ProductLoteService::class)->garantirLoteInicial($this->record->fresh());
            $this->loadProductLotes($this->record->fresh());
        }

        Notification::make()
            ->title('Produto gravado com sucesso.')
            ->success()
            ->send();

        $this->flashOrcamentoReturnContextAfterProductSave();
    }
}
