<?php

namespace App\Filament\Concerns;

use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

trait InteractsWithErpListPage
{
    public ?int $highlightedRecordId = null;

    abstract protected static function erpListPageClass(): string;

    protected function erpListEntityName(): string
    {
        return 'registro';
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
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

    protected function getHeaderActions(): array
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
            'erp-list-page',
            static::erpListPageClass(),
        ];
    }

    protected function applyErpListSelection(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->recordAction('highlightRecord')
            ->recordClasses(function (Model $record): string {
                $classes = $this->erpListRecordClasses($record);

                if ($this->highlightedRecordId === $record->getKey()) {
                    $classes[] = 'erp-row-selected';
                }

                return implode(' ', array_filter($classes));
            });
    }

    /**
     * Classes extras por linha (ex.: destacar vencidos). Sobrescreva na página.
     *
     * @return array<int, string>
     */
    protected function erpListRecordClasses(Model $record): array
    {
        return [];
    }

    public function mountInteractsWithErpListPage(): void
    {
        $this->loadTable();
    }

    public function highlightRecord(int | string $recordId): void
    {
        $this->highlightedRecordId = (int) $recordId;
    }

    protected function clearListSelection(): void
    {
        $this->highlightedRecordId = null;
    }

    protected function highlightedRecordIdOrNotify(string $action): ?int
    {
        if ($this->highlightedRecordId) {
            return $this->highlightedRecordId;
        }

        Notification::make()
            ->title('Selecione ' . $this->erpListSelectPrompt($action) . '.')
            ->warning()
            ->send();

        return null;
    }

    protected function defaultErpListSelectPrompt(string $action): string
    {
        $entity = $this->erpListEntityName();

        return match ($action) {
            'edit' => "{$entity} na lista",
            'delete' => "{$entity} para excluir",
            default => $entity,
        };
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return $this->defaultErpListSelectPrompt($action);
    }

    /**
     * Configuração base repassada ao JS compartilhado (erp-list.js).
     *
     * @return array<string, mixed>
     */
    protected function baseErpListKeyboardConfig(): array
    {
        return [
            'pageClass' => static::erpListPageClass(),
            'searchInput' => '.erp-list__input',
            'create' => 'createRecord',
            'edit' => 'editRecord',
            'delete' => 'deleteRecord',
            'refresh' => 'refreshTable',
            'extraKeys' => [],
        ];
    }

    /**
     * Sobrescreva em cada tela para customizar atalhos/ações Livewire.
     *
     * @return array<string, mixed>
     */
    protected function customErpListKeyboardConfig(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function erpListKeyboardConfig(): array
    {
        return [
            ...$this->baseErpListKeyboardConfig(),
            ...$this->customErpListKeyboardConfig(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getErpListKeyboardConfigForView(): array
    {
        return $this->erpListKeyboardConfig();
    }

    public function refreshTable(): void
    {
        $this->resetTable();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');

        $this->redirect(filament()->getUrl());
    }

    public function modulePending(string $module): void
    {
        Notification::make()
            ->title($module)
            ->body('Em implementação.')
            ->info()
            ->send();
    }
}
