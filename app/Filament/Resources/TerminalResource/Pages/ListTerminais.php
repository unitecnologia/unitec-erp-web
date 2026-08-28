<?php

namespace App\Filament\Resources\TerminalResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\TerminalResource;
use App\Filament\Resources\TerminalResource\Pages\Concerns\ManagesTerminalAparelhos;
use App\Filament\Resources\TerminalResource\Pages\Concerns\ManagesTerminalMasterDetail;
use App\Models\Terminal;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\License\DeviceLicenseService;
use App\Support\Erp\Pdv\TerminalResolver;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListTerminais extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesTerminalAparelhos;
    use ManagesTerminalMasterDetail;

    protected static string $resource = TerminalResource::class;

    protected static ?string $title = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Configurações de Terminais');
        $this->bootTerminalMasterDetail();

        $tab = trim((string) request()->query('tab', ''));
        if ($tab !== '' && in_array($tab, $this->terminalTabKeys(), true)) {
            $this->activeTerminalTab = $tab;
        }
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-terminais-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um terminal';
    }

    protected function customErpListKeyboardConfig(): array
    {
        if ($this->activeTerminalTab === 'aparelhos') {
            return [
                'create' => null,
                'edit' => null,
                'delete' => null,
                'extraKeys' => [
                    'F2' => ['method' => 'autorizarAparelhoSelecionado'],
                    'F4' => ['method' => 'revogarAparelhoSelecionado'],
                ],
            ];
        }

        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Nome'),
            ])
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $empresaId = TerminalResolver::make()->resolveEmpresaId();

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
    }

    /**
     * @return Collection<int, Terminal>
     */
    public function getTerminalsProperty(): Collection
    {
        $empresaId = TerminalResolver::make()->resolveEmpresaId();

        if (! $empresaId) {
            return new Collection;
        }

        $columns = [
            'id',
            'nome',
            'ip',
            'empresa_id',
            'ativo',
            'numero_logico_terminal',
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('terminais', 'categoria_licenca')) {
            $columns = [
                ...$columns,
                'categoria_licenca',
                'origens_dispositivo',
                'device_uuid',
                'device_last_seen_at',
            ];
        }

        return Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('nome', '!=', '')
            ->orderBy('nome')
            ->get($columns);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.terminais.form.window'),
            ]);
    }

    public function selectTerminal(int $recordId): void
    {
        $this->selectTerminalRecord($recordId);
    }

    public function deleteTerminal(): void
    {
        if ($this->isNewTerminal) {
            Notification::make()
                ->title('Nenhum terminal gravado para excluir.')
                ->warning()
                ->send();

            return;
        }

        $recordId = $this->editingTerminalId ?? $this->highlightedRecordId;

        if (! $recordId) {
            $this->highlightedRecordIdOrNotify('delete');

            return;
        }

        $terminal = Terminal::query()->find($recordId);

        if (! $terminal) {
            return;
        }

        if ((int) session('erp.terminal_id') === (int) $terminal->id) {
            TerminalResolver::make()->forget();
        }

        $devices = app(DeviceLicenseService::class);
        $isPdvTerminal = $devices->isPdvOfflineTerminal($terminal);

        if ($isPdvTerminal) {
            $devices->releasePdvOfflineTerminalBeforeDelete($terminal);
        }

        $terminal->delete();
        $this->afterTerminalDeleted();

        $notification = Notification::make()
            ->title('Terminal excluído.')
            ->success();

        if ($isPdvTerminal) {
            $notification->body('O número PDV pode ser usado novamente na carga do caixa.');
        }

        $notification->send();
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        $classes = array_values(array_filter(
            parent::getPageClasses(),
            fn (string $class): bool => ! in_array($class, ['erp-list-page', static::erpListPageClass()], true),
        ));

        return [
            ...$classes,
            'erp-form-page',
            'erp-terminais-form-page',
        ];
    }
}
