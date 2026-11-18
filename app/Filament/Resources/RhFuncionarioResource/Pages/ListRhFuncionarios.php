<?php

namespace App\Filament\Resources\RhFuncionarioResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhFuncionarioResource;
use App\Filament\Resources\RhFuncionarioResource\Pages\Concerns\ManagesRhFuncionarioFormModal;
use App\Models\RhFuncionario;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpOnboarding;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Rh\OperadorFromFuncionarioSync;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListRhFuncionarios extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use ManagesRhFuncionarioFormModal;
    use ManagesErpSearchColumn {
        ManagesErpSearchColumn::updatedSearchColumn insteadof InteractsWithErpSimpleListPage;
    }

    protected static string $resource = RhFuncionarioResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'nome';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->rhFuncionarioForm = $this->blankRhFuncionarioForm();

        $onboardingTitle = ErpOnboarding::screenTitle();
        ErpScreen::set($onboardingTitle ?? 'RH — Funcionários');

        if (ErpOnboarding::step() === ErpOnboarding::STEP_COLABORADOR && ! $this->rhFuncionarioModalOpen) {
            $this->createRhFuncionario();
            $this->rhFuncionarioForm['eh_operador'] = true;
            $this->captureRhFuncionarioFormBaseline();
        }
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['codigo', 'nome', 'cpf'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'nome';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-funcionarios-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um funcionário';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-rh-funcionarios__search-text';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createRhFuncionario';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editRhFuncionario';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteRhFuncionario';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhFuncionarioResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'inativos', 'todos'], true) ? (string) $value : 'ativos';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->with(['cargo', 'departamento']);

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $query = match ($this->statusFilter) {
            'ativos' => $query->where('ativo', true)->whereNull('data_demissao'),
            'inativos' => $query->where(fn (Builder $q) => $q->where('ativo', false)->orWhereNotNull('data_demissao')),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applySimpleLocalSearch($query, $this->localSearch, ['codigo', 'nome', 'cpf']);
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.shared.logistica-simple-screen')
                    ->viewData([
                        'pageClass' => 'erp-rh-funcionarios',
                        'nomeSearchLabel' => 'NOME',
                        'deleteHint' => 'funcionário',
                        'searchFields' => [
                            'codigo' => 'CÓDIGO',
                            'nome' => 'NOME',
                            'cpf' => 'CPF',
                        ],
                    ]),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.shared.erp-status-filters')
                    ->viewData(['pageClass' => 'erp-rh-funcionarios']),
                View::make('filament.components.erp.shared.logistica-simple-action-bar')
                    ->viewData([
                        'pageClass' => 'erp-rh-funcionarios',
                        'createMethod' => 'createRhFuncionario',
                        'editMethod' => 'editRhFuncionario',
                        'deleteMethod' => 'deleteRhFuncionario',
                    ]),
                View::make('filament.components.erp.rh-funcionarios.form-modal'),
            ]);
    }

    public function deleteRhFuncionario(): void
    {
        if ($this->rhFuncionarioModalOpen) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $record = RhFuncionario::query()
            ->when($empresaId > 0, fn (Builder $q) => $q->where('empresa_id', $empresaId))
            ->find($recordId);

        if (! $record) {
            Notification::make()
                ->title('Funcionário não encontrado.')
                ->warning()
                ->send();

            return;
        }

        // Preserva histórico: só desativa o vendedor vinculado (sem DELETE).
        if (filled($record->vendedor_id)) {
            (new OperadorFromFuncionarioSync)->desativarOperador(
                $record,
                $record->vendedor()->first()
            );
        }

        DB::transaction(function () use ($record): void {
            $snapshot = [
                'id' => (int) $record->id,
                'codigo' => $record->codigo,
                'nome' => $record->nome,
                'ativo' => (bool) $record->ativo,
                'vendedor_id' => $record->vendedor_id ? (int) $record->vendedor_id : null,
                'tipo_vinculo' => $record->tipo_vinculo ?? null,
            ];

            $record->delete();

            app(ErpOperacaoLogService::class)->registrar(
                operacao: 'EXCLUIR_RH_FUNCIONARIO',
                resumo: 'Funcionário RH excluído: '.($snapshot['nome'] ?: '#'.$snapshot['id']).'.',
                origem: 'rh_funcionarios',
                documentoTipo: 'rh_funcionario',
                documentoId: (int) $snapshot['id'],
                documentoNumero: (string) ($snapshot['codigo'] ?: $snapshot['id']),
                detalhes: [
                    'acao' => 'excluir',
                    'antes' => $snapshot,
                ],
                empresaId: ErpContext::currentEmpresaId(),
            );
        });

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Funcionário excluído.')
            ->success()
            ->send();
    }
}
