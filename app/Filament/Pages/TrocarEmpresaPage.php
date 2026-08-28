<?php

namespace App\Filament\Pages;

use App\Models\Empresa;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class TrocarEmpresaPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $title = '';

    protected static ?string $slug = 'trocar-empresa';

    protected static bool $shouldRegisterNavigation = false;

    public ?int $selectedEmpresaId = null;

    public function mount(): void
    {
        ErpScreen::set('Trocar Empresa');

        $this->selectedEmpresaId = ErpContext::currentEmpresaId();
    }

    public static function canAccess(): bool
    {
        return Auth::check();
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
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
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
            'erp-trocar-empresa-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.trocar-empresa.screen'),
            ]);
    }

    /**
     * @return list<array{id: int, label: string, cnpj: string, atual: bool}>
     */
    public function empresasDisponiveis(): array
    {
        $user = Auth::user();
        $ids = $user?->accessibleEmpresaIds() ?? [];
        $atualId = ErpContext::currentEmpresaId();

        if ($ids === []) {
            return [];
        }

        return Empresa::query()
            ->whereIn('id', $ids)
            ->where('ativo', true)
            ->orderByRaw('COALESCE(NULLIF(fantasia, ""), NULLIF(nome, ""), razao_social) ASC')
            ->get(['id', 'nome', 'fantasia', 'razao_social', 'cnpj'])
            ->map(fn (Empresa $e): array => [
                'id' => (int) $e->id,
                'label' => (string) ($e->fantasia ?: ($e->nome ?: $e->razao_social)),
                'cnpj' => $this->formatCnpj((string) ($e->cnpj ?? '')),
                'atual' => $atualId !== null && (int) $e->id === (int) $atualId,
            ])
            ->values()
            ->all();
    }

    public function selecionarEmpresa(int $empresaId): void
    {
        if (! ErpContext::userCanAccessEmpresa($empresaId)) {
            Notification::make()
                ->title('Você não tem acesso a esta empresa.')
                ->danger()
                ->send();

            return;
        }

        $this->selectedEmpresaId = $empresaId;
    }

    public function selecionarEConfirmar(int $empresaId): void
    {
        $this->selecionarEmpresa($empresaId);
        $this->confirmarTrocaEmpresa();
    }

    public function confirmarTrocaEmpresa(): void
    {
        $empresaId = (int) ($this->selectedEmpresaId ?? 0);

        if ($empresaId <= 0) {
            Notification::make()
                ->title('Selecione uma empresa.')
                ->warning()
                ->send();

            return;
        }

        if (! ErpContext::userCanAccessEmpresa($empresaId)) {
            Notification::make()
                ->title('Você não tem acesso a esta empresa.')
                ->danger()
                ->send();

            return;
        }

        $atualId = ErpContext::currentEmpresaId();

        if ($atualId !== null && $empresaId === (int) $atualId) {
            Notification::make()
                ->title('Esta já é a empresa ativa.')
                ->info()
                ->send();

            return;
        }

        session(['erp_empresa_id' => $empresaId]);
        ErpContext::clearMemo();

        $nome = Empresa::query()->find($empresaId);
        $label = $nome
            ? (string) ($nome->fantasia ?: ($nome->nome ?: $nome->razao_social))
            : 'empresa selecionada';

        Notification::make()
            ->title('Empresa alterada: '.$label)
            ->success()
            ->send();

        $this->redirect(filament()->getUrl(), navigate: false);
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }

    protected function formatCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D+/', '', $cnpj) ?? '';

        if (strlen($digits) !== 14) {
            return $cnpj !== '' ? $cnpj : '—';
        }

        return substr($digits, 0, 2).'.'
            .substr($digits, 2, 3).'.'
            .substr($digits, 5, 3).'/'
            .substr($digits, 8, 4).'-'
            .substr($digits, 12, 2);
    }
}
