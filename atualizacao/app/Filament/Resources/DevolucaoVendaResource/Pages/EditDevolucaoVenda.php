<?php

namespace App\Filament\Resources\DevolucaoVendaResource\Pages;

use App\Filament\Resources\DevolucaoVendaResource;
use App\Filament\Resources\DevolucaoVendaResource\Pages\Concerns\ErpDevolucaoVendaFormPage;
use App\Models\DevolucaoVenda;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDevolucaoVenda extends EditRecord
{
    use ErpDevolucaoVendaFormPage;

    protected static string $resource = DevolucaoVendaResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        ErpScreen::set('Lançamento Devolução de Venda');

        /** @var DevolucaoVenda $devolucao */
        $devolucao = $this->record;

        if (! $devolucao->isEditable()) {
            Notification::make()
                ->title('Devolução não pode ser alterada.')
                ->body('Somente devoluções abertas podem ser editadas.')
                ->warning()
                ->send();

            $this->redirect(DevolucaoVendaResource::getUrl('index'));

            return;
        }

        $this->loadDevolucaoFormFromRecord($devolucao);
    }

    protected function getRedirectUrl(): string
    {
        return DevolucaoVendaResource::getUrl('index');
    }
}
