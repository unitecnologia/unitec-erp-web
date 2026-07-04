<?php

namespace App\Filament\Resources\OrcamentoResource\Pages;

use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\OrcamentoResource\Pages\Concerns\ErpOrcamentoFormPage;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\CreateRecord;

class CreateOrcamento extends CreateRecord
{
    use ErpOrcamentoFormPage;

    protected static string $resource = OrcamentoResource::class;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Lançamento de Orçamento');
        $this->initializeOrcamentoFormDefaults();
    }

    protected function getRedirectUrl(): string
    {
        return OrcamentoResource::getUrl('index');
    }
}
