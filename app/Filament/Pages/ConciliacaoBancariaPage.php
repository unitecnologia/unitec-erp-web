<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ExtratoLancamentoResource;
use App\Support\Erp\ErpAccess;
use Filament\Pages\Page;

/**
 * Compatibilidade: a C1 passou a usar ExtratoLancamentoResource (padrão RH ListRecords).
 * Mantém a rota antiga apenas como redirecionamento.
 */
class ConciliacaoBancariaPage extends Page
{
    protected static ?string $slug = 'conciliacao-bancaria-legacy';

    protected static ?string $routePath = 'conciliacao-bancaria-legacy';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.conciliacao-bancaria-redirect';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->redirect(ExtratoLancamentoResource::getUrl('index'), navigate: false);
    }

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('conciliacao_bancaria.access');
    }
}
