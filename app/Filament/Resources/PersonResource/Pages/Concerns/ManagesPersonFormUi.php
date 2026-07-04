<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

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

    protected function syncTipoContribuinteFromIe(): void
    {
        if (filled($this->data['rg_ie'] ?? null)) {
            $this->data['tipo_contribuinte'] = 'contribuinte';
        }
    }
}
