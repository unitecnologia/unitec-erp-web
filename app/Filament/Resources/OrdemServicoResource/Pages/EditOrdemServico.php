<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages;

use App\Filament\Resources\OrdemServicoResource;
use App\Filament\Resources\OrdemServicoResource\Pages\Concerns\ErpOrdemServicoFormPage;
use App\Models\OrdemServico;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrdemServico extends EditRecord
{
    use ErpOrdemServicoFormPage;

    protected static string $resource = OrdemServicoResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        ErpScreen::set('Lançamento OS');

        /** @var OrdemServico $ordem */
        $ordem = $this->record;

        if (! $ordem->isEditable()) {
            Notification::make()
                ->title('Ordem de serviço não pode ser alterada.')
                ->body('Somente OS abertas ou em andamento podem ser editadas.')
                ->warning()
                ->send();

            $this->redirect(OrdemServicoResource::getUrl('index'));

            return;
        }

        $this->loadOsFormFromRecord($ordem);
    }

    protected function getRedirectUrl(): string
    {
        return OrdemServicoResource::getUrl('index');
    }
}
