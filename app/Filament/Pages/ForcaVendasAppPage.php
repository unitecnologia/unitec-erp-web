<?php

namespace App\Filament\Pages;

use App\Support\ForcaVendas\ForcaVendasPairing;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;

class ForcaVendasAppPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $navigationLabel = 'App Força de Vendas';

    protected static ?string $title = 'App Força de Vendas';

    protected static ?string $slug = 'forca-vendas-app';

    protected string $view = 'filament.pages.forca-vendas-app';

    public string $ipServidor = '';

    public static function canAccess(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->ipServidor = ForcaVendasPairing::detectServerIp();
    }

    #[Computed]
    public function baseUrl(): string
    {
        return ForcaVendasPairing::baseUrl($this->ipServidor);
    }

    public function porta(): int
    {
        return ForcaVendasPairing::SERVE_PORT;
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'App Força de Vendas — Como conectar';
    }
}
