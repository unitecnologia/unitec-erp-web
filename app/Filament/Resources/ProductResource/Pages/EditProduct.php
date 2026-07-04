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

        $this->syncProductFormData();
        $this->mountProductPhoto();
        $this->loadProductGrades($this->record);
        $this->loadProductCompositions($this->record);
        $this->loadProductPriceTableItems($this->record);
        $this->loadProductPriceHistories($this->record);
        $this->loadProductImeis($this->record);
        $this->loadProductReservas($this->record);
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

        Notification::make()
            ->title('Produto gravado com sucesso.')
            ->success()
            ->send();

        $this->flashOrcamentoReturnContextAfterProductSave();
    }
}
