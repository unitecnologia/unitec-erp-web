<?php

namespace App\Filament\Resources\OrcamentoResource\Pages;

use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\OrcamentoResource\Pages\Concerns\ErpOrcamentoFormPage;
use App\Models\Orcamento;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrcamento extends EditRecord
{
    use ErpOrcamentoFormPage;

    protected static string $resource = OrcamentoResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        ErpScreen::set('Lançamento de Orçamento');

        /** @var Orcamento $orcamento */
        $orcamento = $this->record;

        if (! $orcamento->isEditable()) {
            Notification::make()
                ->title('Orçamento não pode ser alterado.')
                ->body('Somente orçamentos abertos podem ser editados.')
                ->warning()
                ->send();

            $this->redirect(OrcamentoResource::getUrl('index'));

            return;
        }

        $this->loadOrcamentoFormFromRecord($orcamento);

        if (session()->pull('erp_orcamento_post_save_prompt')) {
            $this->openPostSavePromptFromSession();
        }
    }

    protected function getRedirectUrl(): string
    {
        return OrcamentoResource::getUrl('index');
    }
}
