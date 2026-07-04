<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpScreen;
use App\Support\Erp\ZeraEstoqueNegativoService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ZeraEstoqueNegativoPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $title = '';

    protected static ?string $slug = 'zera-estoque-negativo';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        ErpScreen::set('Zera Estoque Negativo');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-list-page', 'erp-comando-page'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.zera-estoque-negativo.screen'),
            ]);
    }

    public function executar(): void
    {
        $count = (new ZeraEstoqueNegativoService())->zerarTodos();

        Notification::make()
            ->title('Estoque negativo zerado.')
            ->body($count . ' produto(s) ajustado(s).')
            ->success()
            ->send();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }
}
