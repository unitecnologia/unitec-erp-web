<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ErpProductFormPage;
use App\Models\Product;
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

        // Um único format+fill (evita syncProductFormData + fill duplicados na abertura).
        $this->data = $this->formatProductFormDataForDisplay($this->form->getState());
        $this->applyEmpresaPrecosToFormData($this->record);
        $this->hydrateNcmDescricaoFromCatalog(fillForm: false);
        $this->form->fill($this->data);
        $this->refreshProductFotoPreviewUrl();
        $this->mountProductPhoto();
        $this->captureLastSavedProductPrices($this->record instanceof Product ? $this->record : null);

        // Tab preço / grade / etc. sob demanda; aba ativa (padrão Estoque) carrega só o necessário.
        $this->ensureProductTabPayload($this->activeFormTab);

        $this->captureProductFormBaseline();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        if ($this->embedsInPdv) {
            return $this->urlWithPdvEmbed(ProductResource::getUrl('index'));
        }

        return $this->erpFormReturnRedirectUrl($this->getProductListRedirectUrl());
    }

    protected function afterSave(): void
    {
        $this->syncProductChildRecords($this->record);
        // Sem syncProductFormData completo (getState + fill + reservas sempre).
        $this->refreshProductFormAfterSave();

        if ($this->record instanceof Product) {
            // Um único uso do record já persistido — evita 2× fresh().
            app(\App\Support\Erp\ProductLoteService::class)->garantirLoteInicial($this->record);

            // Lotes só na UI se a aba estiver aberta (gravação de lote é modal próprio).
            if ($this->activeFormTab === 'lotes') {
                $this->loadProductLotes($this->record);
            }
        }

        Notification::make()
            ->title('Produto gravado com sucesso.')
            ->success()
            ->send();

        $this->flashOrcamentoReturnContextAfterProductSave();
    }
}
