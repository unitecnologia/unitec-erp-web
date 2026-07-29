<?php

namespace App\Filament\Resources\DevolucaoVendaResource\Pages;

use App\Filament\Resources\DevolucaoVendaResource;
use App\Filament\Resources\DevolucaoVendaResource\Pages\Concerns\ErpDevolucaoVendaFormPage;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\CreateRecord;

class CreateDevolucaoVenda extends CreateRecord
{
    use ErpDevolucaoVendaFormPage;

    protected static string $resource = DevolucaoVendaResource::class;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Lançamento Devolução de Venda');
        $this->initializeDevolucaoFormDefaults();
    }

    protected function getRedirectUrl(): string
    {
        return DevolucaoVendaResource::getUrl('index');
    }
}
