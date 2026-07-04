<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Concerns\ManagesPdvUi;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Pdv\TerminalResolver;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use BackedEnum;

class PdvPage extends Page
{
    use ManagesPdvUi;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'PDV';

    protected static ?string $title = '';

    protected static ?string $slug = 'pdv';

    protected static bool $shouldRegisterNavigation = false;

    public static function getRoutePath(\Filament\Panel $panel): string
    {
        return 'pdv';
    }

    public function mount(): void
    {
        if (! PdvConfig::make()->usarPdvRetaguarda()) {
            Notification::make()
                ->title('PDV desabilitado.')
                ->body('Ative "Usar PDV no Retaguarda" nos parâmetros da empresa.')
                ->warning()
                ->send();

            $this->redirect(Dashboard::getUrl(), navigate: false);

            return;
        }

        ErpScreen::set('PDV');

        TerminalResolver::make()->resolveOrCreateDefault();

        $this->loadPdvSessionState();
        $this->loadCupomFromSession();

        if (! $this->caixaAberto) {
            $this->aberturaForm['valor'] = '0,00';
            $this->openPdvModal('abrir_caixa');
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-pdv-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.pdv.screen'),
            ]);
    }
}
