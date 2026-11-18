<?php

namespace App\Filament\Resources\OrcamentoResource\Pages;

use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\OrcamentoResource\Pages\Concerns\ErpOrcamentoFormPage;
use App\Models\Orcamento;
use App\Models\User;
use App\Support\Erp\ErpScreen;
use App\Support\VendasInternas\VendasInternasEdicaoLockService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

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

        $user = Auth::user();
        if ($user instanceof User) {
            try {
                // Orçamento com vínculo VI: bloqueia o app enquanto o ERP edita.
                app(VendasInternasEdicaoLockService::class)
                    ->adquirirErpPorOrcamentoId((int) $orcamento->id, $user);
            } catch (\RuntimeException $e) {
                Notification::make()
                    ->title('Orçamento não pode ser alterado.')
                    ->body($e->getMessage())
                    ->warning()
                    ->send();

                $this->redirect(OrcamentoResource::getUrl('index'));

                return;
            }
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
