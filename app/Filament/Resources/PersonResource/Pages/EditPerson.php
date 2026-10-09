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
        if (request()->boolean('pdv')) {
            $this->embedsInPdv = true;
        }

        if (request()->boolean('orcamento')) {
            $this->embedsInOrcamento = true;
        }

        parent::mount($record);

        if (blank($this->data['regime_tributario'] ?? null)) {
            $this->data['regime_tributario'] = '';
        }

        ErpScreen::set('Cadastro de Pessoas');

        $this->loadPersonContacts($this->record);
        $this->loadPersonVisitaDias($this->record);
        $this->mountPersonPhoto();
    }

    protected function afterSave(): void
    {
        $this->commitPersonPhotoAfterSave();
        $this->syncPersonContacts($this->record);
        $this->syncPersonVisitaDias($this->record);
        $this->loadPersonContacts($this->record);
        $this->loadPersonVisitaDias($this->record);
        $this->flashOrcamentoReturnContextAfterPersonSave();
    }

    protected function getRedirectUrl(): string
    {
        if ($this->embedsInPdv) {
            return $this->getPersonListRedirectUrl();
        }

        return $this->erpFormReturnRedirectUrl($this->getPersonListRedirectUrl());
    }
}
