<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpScreen;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ImpressaoEtiquetasNovoPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $title = '';

    protected static ?string $slug = 'impressao-etiquetas-novo';

    protected static bool $shouldRegisterNavigation = false;

    public string $tipoBusca = 'codigo_barras';

    public string $termoBusca = '';

    public int $qtdEtiquetas = 1;

    public function mount(): void
    {
        ErpScreen::set('Etiquetas');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-list-page', 'erp-etiquetas-novo-page'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.etiquetas-novo.screen'),
            ]);
    }

    public function limpar(): void
    {
        $this->termoBusca = '';
        $this->qtdEtiquetas = 1;
    }

    public function imprimir(): void
    {
        $this->modulePending('Impressão de etiquetas (Fase 2)');
    }

    public function modulePending(string $module): void
    {
        Notification::make()->title($module)->body('Em implementação.')->info()->send();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }
}
