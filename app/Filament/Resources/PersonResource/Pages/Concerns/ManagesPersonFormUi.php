<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

use App\Models\Person;
use Filament\Notifications\Notification;

trait ManagesPersonFormUi
{
    public string $activeFormTab = 'dados';

    public function setActiveFormTab(string $tab): void
    {
        $this->activeFormTab = $tab;
    }

    public function modulePending(string $module): void
    {
        Notification::make()
            ->title($module)
            ->body('Em implementação.')
            ->info()
            ->send();
    }

    public function updatedDataRgIe(?string $value): void
    {
        $this->syncTipoContribuinteFromIe();
    }

    public function updatedDataPessoaTipo(?string $value): void
    {
        if ($value === Person::PESSOA_FISICA) {
            $this->data['tipo_contribuinte'] = 'nao_contribuinte';
        }
    }

    public function updatedDataCpfCnpj(?string $value): void
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if (strlen($digits) === 11) {
            $this->data['pessoa_tipo'] = Person::PESSOA_FISICA;
            $this->data['tipo_contribuinte'] = 'nao_contribuinte';
        } elseif (strlen($digits) === 14) {
            $this->data['pessoa_tipo'] = Person::PESSOA_JURIDICA;
        }
    }

    protected function syncTipoContribuinteFromIe(): void
    {
        if (($this->data['pessoa_tipo'] ?? null) === Person::PESSOA_FISICA) {
            return;
        }

        $ie = mb_strtoupper(trim((string) ($this->data['rg_ie'] ?? '')), 'UTF-8');

        if ($ie === '' || $ie === '-') {
            $this->data['tipo_contribuinte'] = 'nao_contribuinte';

            return;
        }

        if (in_array($ie, ['ISENTO', 'ISENTA'], true)) {
            $this->data['tipo_contribuinte'] = 'isento';

            return;
        }

        $this->data['tipo_contribuinte'] = 'contribuinte';
    }
}
