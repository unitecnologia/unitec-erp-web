<?php

namespace App\Filament\Resources\EmpresaResource\Pages;

use App\Filament\Resources\EmpresaResource;
use App\Filament\Resources\EmpresaResource\Pages\Concerns\ErpEmpresaFormPage;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\EditRecord;

class EditEmpresa extends EditRecord
{
    use ErpEmpresaFormPage;

    protected static string $resource = EmpresaResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        ErpScreen::set('Cadastro de Empresa');

        if (isset($this->data['codigo'])) {
            $this->data['codigo'] = (string) $this->data['codigo'];
        }

        $this->normalizeEmpresaSlugFormData();

        $this->prepareEmpresaParametrosForForm();

        if (static::normalizeOptionalCnpjRepresentante($this->data['cnpj_representante'] ?? null) === null) {
            $this->data['cnpj_representante'] = '';
            $this->form->fill($this->data);
        }

        $this->mountEmpresaLogo();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getEmpresaListRedirectUrl();
    }
}
