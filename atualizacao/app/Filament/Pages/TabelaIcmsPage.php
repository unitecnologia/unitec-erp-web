<?php

namespace App\Filament\Pages;

use App\Models\IcmsAliquota;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Fiscal\IcmsAliquotaTabela;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TabelaIcmsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $title = '';

    protected static ?string $slug = 'tabela-icms';

    protected static bool $shouldRegisterNavigation = false;

    /** @var list<string> */
    public array $ufs = [];

    /**
     * Matriz origem â†’ destino â†’ alÃ­quota.
     *
     * @var array<string, array<string, float|string>>
     */
    public array $matrix = [];

    public string $locateUf = '';

    public ?string $highlightUf = null;

    public ?string $editOrigem = null;

    public ?string $editDestino = null;

    public string $editValue = '';

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('tabela_icms.access');
    }

    public function mount(): void
    {
        ErpScreen::set('Tabela ICMS');

        $this->ufs = IcmsAliquotaTabela::ufs();
        $this->loadMatrix();

        if (IcmsAliquota::query()->count() === 0) {
            IcmsAliquota::seedPadrao2026();
            $this->loadMatrix();

            Notification::make()
                ->title('Tabela ICMS carregada com o padrÃ£o 2026.')
                ->success()
                ->send();
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        // Do NOT use erp-list-page: erp-grid.css targets Filament tables and
        // collapses this custom matrix (no .fi-ta) into a blank panel.
        return [...parent::getPageClasses(), 'erp-tabela-icms-page'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.tabela-icms.screen'),
            ]);
    }

    public function loadMatrix(): void
    {
        $this->matrix = IcmsAliquota::matriz();
    }

    public function startEdit(string $ufOrigem, string $ufDestino): void
    {
        $origem = strtoupper(trim($ufOrigem));
        $destino = strtoupper(trim($ufDestino));

        if (! in_array($origem, $this->ufs, true) || ! in_array($destino, $this->ufs, true)) {
            return;
        }

        $this->editOrigem = $origem;
        $this->editDestino = $destino;
        $rate = (float) ($this->matrix[$origem][$destino] ?? 0);
        $this->editValue = number_format($rate, 2, ',', '');
    }

    public function commitEdit(): void
    {
        if ($this->editOrigem === null || $this->editDestino === null) {
            return;
        }

        $origem = $this->editOrigem;
        $destino = $this->editDestino;
        $value = $this->editValue;

        $this->cancelEdit();
        $this->saveCell($origem, $destino, $value);
    }

    public function cancelEdit(): void
    {
        $this->editOrigem = null;
        $this->editDestino = null;
        $this->editValue = '';
    }

    public function saveCell(string $ufOrigem, string $ufDestino, mixed $value): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'tabela_icms.update')) {
            $this->loadMatrix();

            return;
        }

        $origem = strtoupper(trim($ufOrigem));
        $destino = strtoupper(trim($ufDestino));

        if (! in_array($origem, $this->ufs, true) || ! in_array($destino, $this->ufs, true)) {
            return;
        }

        $normalized = is_string($value)
            ? str_replace(',', '.', trim($value))
            : $value;

        $validator = Validator::make(
            ['aliquota' => $normalized],
            ['aliquota' => ['required', 'numeric', 'min:0', 'max:100']],
            [],
            ['aliquota' => 'alÃ­quota'],
        );

        if ($validator->fails()) {
            Notification::make()
                ->title($validator->errors()->first())
                ->warning()
                ->send();

            $this->loadMatrix();

            return;
        }

        $aliquota = round((float) $normalized, 2);

        IcmsAliquota::query()->updateOrCreate(
            [
                'uf_origem' => $origem,
                'uf_destino' => $destino,
            ],
            [
                'aliquota' => $aliquota,
            ],
        );

        $this->matrix[$origem][$destino] = $aliquota;
    }

    public function locateUf(): void
    {
        $uf = strtoupper(trim($this->locateUf));
        $this->locateUf = $uf;

        if ($uf === '' || ! in_array($uf, $this->ufs, true)) {
            Notification::make()
                ->title('Informe uma UF vÃ¡lida (ex.: SP).')
                ->warning()
                ->send();

            return;
        }

        $this->highlightUf = $uf;
        $this->dispatch('erp-tabela-icms-scroll-uf', uf: $uf);
    }

    public function updatedLocateUf(string $value): void
    {
        $this->locateUf = strtoupper(preg_replace('/[^a-zA-Z]/', '', $value) ?? '');
    }

    public function focusLocate(): void
    {
        $this->dispatch('erp-tabela-icms-focus-locate');
    }

    public function handleEscape(): void
    {
        if ($this->editOrigem !== null) {
            $this->cancelEdit();

            return;
        }

        $this->closeScreen();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }
}
