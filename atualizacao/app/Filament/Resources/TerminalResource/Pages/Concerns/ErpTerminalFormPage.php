<?php

namespace App\Filament\Resources\TerminalResource\Pages\Concerns;

use App\Filament\Resources\TerminalResource;
use App\Models\Terminal;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\Pdv\TerminalResolver;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

trait ErpTerminalFormPage
{
    public string $activeTerminalTab = 'impressora';

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-terminais-form-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.terminais.form.window'),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName())
                    ->extraAttributes(['class' => 'erp-pcad__filament-hidden']),
            ]);
    }

    public function selectTerminalTab(string $tab): void
    {
        if (in_array($tab, $this->terminalTabKeys(), true)) {
            $this->activeTerminalTab = $tab;
        }
    }

    /**
     * @return list<string>
     */
    public function terminalTabKeys(): array
    {
        return ['configuracoes', 'balanca', 'sat', 'tef'];
    }

    public function saveForm(): void
    {
        if (blank($this->data['velocidade'] ?? null)) {
            Notification::make()
                ->title('Selecione a velocidade de impressão.')
                ->warning()
                ->send();

            return;
        }

        if ($this instanceof EditRecord) {
            $this->save();
        } else {
            /** @var CreateRecord $this */
            $this->create();
        }
    }

    public function cancelForm(): void
    {
        ErpScreen::set('Terminais');

        $this->redirect(TerminalResource::getUrl('index'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mergeLivewireFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mergeLivewireFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeLivewireFormData(array $data): array
    {
        $merged = array_merge($data, $this->data ?? []);
        $merged = ErpUppercase::normalizeFormData($merged);

        if (blank($merged['empresa_id'] ?? null)) {
            $merged['empresa_id'] = TerminalResolver::make()->resolveEmpresaId();
        }

        if (($merged['tipo_fechamento'] ?? null) !== '0' && ($merged['tipo_fechamento'] ?? null) !== 0) {
            $merged['meia_folha'] = false;
        }

        return $merged;
    }

    protected function afterCreate(): void
    {
        $this->afterTerminalPersisted();
    }

    protected function afterSave(): void
    {
        $this->afterTerminalPersisted();
    }

    protected function afterTerminalPersisted(): void
    {
        if (! property_exists($this, 'record') || ! $this->record) {
            return;
        }

        TerminalResolver::make()->remember($this->record);

        Notification::make()
            ->title('Terminal gravado.')
            ->body('Reabra o PDV para aplicar as configurações deste terminal.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        ErpScreen::set('Terminais');

        return TerminalResource::getUrl('index');
    }

    /**
     * @return array<string, mixed>
     */
    protected static function defaultTerminalFormData(): array
    {
        $resolver = TerminalResolver::make();
        $empresaId = $resolver->resolveEmpresaId();

        return [
            ...Terminal::defaultAttributes($empresaId),
            'empresa_id' => $empresaId,
            'nome' => $resolver->resolveMachineName(),
            'ip' => $resolver->resolveClientIp(),
            'velocidade' => 9600,
            'nvias' => 1,
            'tipo_impressora' => '1',
            'tipo_fechamento' => '0',
        ];
    }
}
