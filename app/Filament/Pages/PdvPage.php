<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpAccess;
use App\Filament\Pages\Concerns\ManagesPdvUi;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Pdv\TerminalResolver;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use BackedEnum;

class PdvPage extends Page
{
    use ManagesPdvUi;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'PDV';

    protected static ?string $title = '';

    protected static ?string $slug = 'pdv';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('pdv.access');
    }

    public static function getRoutePath(\Filament\Panel $panel): string
    {
        return 'pdv';
    }

    public function mount(): void
    {
        ErpScreen::set('PDV');

        $terminal = TerminalResolver::make()->resolveOrCreateDefault();

        $user = Auth::user();
        if ($user && ! $user->podeOperarPdvNoTerminal($terminal)) {
            $nome = trim((string) ($terminal?->nome ?? '')) ?: 'este terminal';

            Notification::make()
                ->title('PDV não liberado para este usuário.')
                ->body('Você não tem permissão para operar no PDV "'.$nome.'". Solicite liberação ao gerente.')
                ->danger()
                ->send();

            $this->redirect(filament()->getUrl());

            return;
        }

        $this->loadPdvSessionState();
        $this->loadCupomFromSession();

        if (! $this->garantirOperadorDoUsuarioLogado()) {
            $this->redirect(filament()->getUrl());

            return;
        }

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
