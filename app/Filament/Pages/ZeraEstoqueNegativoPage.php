<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ManagesZeraEstoque;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ZeraEstoqueNegativoPage extends Page
{
    use ManagesZeraEstoque;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $title = '';

    protected static ?string $slug = 'zera-estoque-negativo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('ajuste_estoque.access');
    }

    public function mount(): void
    {
        ErpScreen::set('Zera Estoque');
        $this->bootZeraEstoqueDefaults();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-list-page', 'erp-zera-estoque-page'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.zera-estoque-negativo.screen'),
            ]);
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }
}
