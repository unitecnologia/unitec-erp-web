<?php

namespace App\Filament\Resources\EmpresaResource\Pages;

use App\Filament\Resources\EmpresaResource;
use App\Filament\Resources\EmpresaResource\Pages\Concerns\ErpEmpresaFormPage;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\CreateRecord;

class CreateEmpresa extends CreateRecord
{
    use ErpEmpresaFormPage;

    protected static string $resource = EmpresaResource::class;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Cadastro de Empresa');

        $defaults = [
            ...($this->data ?? []),
            ...static::defaultEmpresaFormData(),
        ];

        $this->data = $defaults;
        $this->form->fill($defaults);
        $this->prepareEmpresaParametrosForForm();
        $this->mountEmpresaLogo();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getEmpresaListRedirectUrl();
    }
}
