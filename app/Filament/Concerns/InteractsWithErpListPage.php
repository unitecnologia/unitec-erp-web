<?php

namespace App\Filament\Concerns;

use App\Support\Erp\ErpDataSyncVersion;
use App\Support\Erp\ErpScreen;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

trait InteractsWithErpListPage
{
    public ?int $highlightedRecordId = null;

    /** Versão local do canal de sync (híbrido em rede). */
    public ?string $erpListSyncVersion = null;

    abstract protected static function erpListPageClass(): string;

    /**
     * Canal de ErpDataSyncVersion para esta lista. Null = sem poll automático.
     */
    protected function erpListSyncChannel(): ?string
    {
        return null;
    }

    /**
     * Intervalo do poll em segundos (só checa versão; não recarrega a grade se não mudou).
     */
    protected function erpListSyncPollSeconds(): int
    {
        return 20;
    }

    public function erpListSyncPollEnabled(): bool
    {
        return $this->erpListSyncChannel() !== null;
    }

    public function erpListSyncPollIntervalSeconds(): int
    {
        return max(5, $this->erpListSyncPollSeconds());
    }

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
     * Classes extras no container da página (ex.: variante NFC-e).
     *
     * @return array<int, string>
     */
    protected function erpListExtraPageClasses(): array
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
            ...$this->erpListExtraPageClasses(),
        ];
    }

    protected function applyErpListSelection(Table $table): Table
    {
        // Filament v4 row clicks call mountTableAction(name), so the Livewire
        // method must also be registered as a table action.
        // Do NOT use ->hidden(): Filament's isDisabled() treats hidden actions as
        // disabled, so mountTableAction silently no-ops and selection never sticks.
        // Toolbar UI is already hidden via .erp-list-page CSS.
        return $table
            ->recordUrl(null)
            ->recordAction('highlightRecord')
            ->pushToolbarActions([
                Action::make('highlightRecord')
                    ->action(function (Model $record): void {
                        $this->highlightRecord($record->getKey());
                    }),
            ])
            ->recordClasses(function (Model $record): string {
                $classes = $this->erpListRecordClasses($record);

                if ((int) $this->highlightedRecordId === (int) $record->getKey()) {
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
        $this->syncErpListSyncVersionFromStore();
    }

    public function pollErpListSync(): void
    {
        $channel = $this->erpListSyncChannel();

        if ($channel === null) {
            $this->skipRender();

            return;
        }

        $current = ErpDataSyncVersion::current($channel);

        if ($this->erpListSyncVersion === null) {
            $this->erpListSyncVersion = $current;
            $this->skipRender();

            return;
        }

        if (hash_equals($this->erpListSyncVersion, $current)) {
            // Sem mudança: não re-renderiza a tabela (era a causa de cliques lentos).
            $this->skipRender();

            return;
        }

        $this->erpListSyncVersion = $current;
        $this->resetTable();
    }

    protected function syncErpListSyncVersionFromStore(): void
    {
        $channel = $this->erpListSyncChannel();

        if ($channel === null) {
            $this->erpListSyncVersion = null;

            return;
        }

        $this->erpListSyncVersion = ErpDataSyncVersion::current($channel);
    }

    public function highlightRecord(int | string $recordId): void
    {
        $this->highlightedRecordId = (int) $recordId;
        // Sem remount: o 2º clique do duplo clique precisa da mesma linha no DOM.
        $this->skipRender();
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
        $this->syncErpListSyncVersionFromStore();

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
