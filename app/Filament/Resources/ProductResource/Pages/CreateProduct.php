<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ErpProductFormPage;
use App\Models\Empresa;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateProduct extends CreateRecord
{
    use ErpProductFormPage;

    protected static string $resource = ProductResource::class;

    public function mount(): void
    {
        if (request()->boolean('pdv')) {
            $this->embedsInPdv = true;
        }

        if (request()->boolean('orcamento')) {
            $this->embedsInOrcamento = true;
        }

        parent::mount();

        ErpScreen::set('Cadastro de Produtos');

        if ($this->embedsInPdv) {
            $this->activeFormTab = 'dados';
        }

        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        $defaults = [
            ...($this->data ?? []),
            ...static::defaultProductFormData($empresa),
        ];

        $defaults = $this->formatProductFormDataForDisplay($defaults);

        $this->data = $defaults;
        $this->form->fill($defaults);
        $this->mountProductPhoto();
        $this->loadProductGrades();
        $this->loadProductCompositions();
        $this->loadProductPriceTableItems();
        $this->loadProductPriceHistories();
        $this->loadProductImeis();
    }

    protected function getRedirectUrl(): string
    {
        if ($this->embedsInPdv) {
            return static::getResource()::getUrl('create') . '?pdv=1';
        }

        if ($this->embedsInOrcamento) {
            return static::getResource()::getUrl('create') . '?orcamento=1';
        }

        return $this->erpFormReturnRedirectUrl($this->getProductListRedirectUrl());
    }

    protected function afterCreate(): void
    {
        \App\Support\Erp\ProductInitialStockService::registerFromInitialStock($this->record);
        $this->syncProductChildRecords($this->record);

        Notification::make()
            ->title('Produto gravado com sucesso.')
            ->success()
            ->send();

        if ($this->embedsInOrcamento) {
            $this->closeEmbedOverlay([
                'produtoCodigo' => (string) ($this->record?->codigo ?? ''),
            ]);

            return;
        }

        $this->flashOrcamentoReturnContextAfterProductSave();

        if ($this->embedsInPdv) {
            $this->closePdvEmbedOverlay();
        }
    }
}
