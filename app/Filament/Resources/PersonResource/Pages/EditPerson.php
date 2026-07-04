<?php

namespace App\Filament\Resources\PersonResource\Pages;

use App\Filament\Resources\PersonResource;
use App\Filament\Resources\PersonResource\Pages\Concerns\ErpPersonFormPage;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\EditRecord;

class EditPerson extends EditRecord
{
    use ErpPersonFormPage;

    protected static string $resource = PersonResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        ErpScreen::set('Cadastro de Pessoas');

        $this->loadPersonContacts($this->record);
        $this->mountPersonPhoto();
    }

    protected function afterSave(): void
    {
        $this->syncPersonContacts($this->record);
        $this->loadPersonContacts($this->record);
        $this->flashOrcamentoReturnContextAfterPersonSave();
    }

    protected function getRedirectUrl(): string
    {
        return $this->erpFormReturnRedirectUrl($this->getPersonListRedirectUrl());
    }
}
