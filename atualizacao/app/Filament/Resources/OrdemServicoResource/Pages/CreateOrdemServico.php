<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages;

use App\Filament\Resources\OrdemServicoResource;
use App\Filament\Resources\OrdemServicoResource\Pages\Concerns\ErpOrdemServicoFormPage;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\CreateRecord;

class CreateOrdemServico extends CreateRecord
{
    use ErpOrdemServicoFormPage;

    protected static string $resource = OrdemServicoResource::class;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Lançamento OS');
        $this->initializeOsFormDefaults();
    }

    protected function getRedirectUrl(): string
    {
        return OrdemServicoResource::getUrl('index');
    }
}
