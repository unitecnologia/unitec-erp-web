<?php

namespace App\Filament\Resources\EmpresaResource\Pages;

use App\Filament\Resources\EmpresaResource;
use App\Filament\Resources\EmpresaResource\Pages\Concerns\ErpEmpresaFormPage;
use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\EmpresaVendaProntaBootstrap;
use App\Support\Erp\ErpOnboarding;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateEmpresa extends CreateRecord
{
    use ErpEmpresaFormPage;

    protected static string $resource = EmpresaResource::class;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set(Empresa::query()->exists()
            ? 'Cadastro de Empresa'
            : 'Primeiro acesso — Cadastro de Empresa');

        $defaults = [
            ...($this->data ?? []),
            ...static::defaultEmpresaFormData(),
        ];

        $this->data = $defaults;
        $this->form->fill($defaults);
        $this->prepareEmpresaParametrosForForm();
        $this->mountEmpresaLogo();
    }

    public function cancelForm(): void
    {
        if (! Empresa::query()->exists()) {
            \Filament\Notifications\Notification::make()
                ->title('Cadastre a empresa para continuar')
                ->warning()
                ->send();

            return;
        }

        ErpScreen::set('Empresa');
        $this->redirect(EmpresaResource::getUrl('index'));
    }

    protected function afterCreate(): void
    {
        /** @var Empresa $empresa */
        $empresa = $this->record;
        $user = Auth::user();

        \Illuminate\Support\Facades\Cache::forget('erp.empresa.exists');

        if ($user instanceof User) {
            $user->forceFill(['empresa_id' => $empresa->id])->save();

            if (! $user->empresas()->whereKey($empresa->id)->exists()) {
                $user->empresas()->attach($empresa->id);
            }
        }

        session(['erp_empresa_id' => (int) $empresa->id]);

        // Estoque LOJA + caixa PDV + terminal Pedido + operador do usuário logado.
        EmpresaVendaProntaBootstrap::forEmpresa($empresa, $user instanceof User ? $user : null);

        // Pronto para vender (não fiscal). NFC-e/certificado fica para depois.
        ErpOnboarding::complete();

        Notification::make()
            ->title('Empresa pronta para vender')
            ->body(
                'Modo não fiscal liberado: estoque LOJA, caixa PDV e operador foram criados. '.
                'Cadastre produtos e abra o PDV. NFC-e pode ser configurada depois.'
            )
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return filament()->getUrl();
    }
}
