<?php

namespace App\Filament\Resources\CfopResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\CfopResource;
use App\Models\Cfop;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Import\CfopFirebirdSync;
use Database\Seeders\CfopSeeder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

class ListCfops extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use NormalizesErpUppercaseFormData;

    protected static string $resource = CfopResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    #[Url(as: 'status')]
    public string $statusFilter = 'todos';

    public bool $showForm = false;

    public ?int $formId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('CFOP');

        // Fonte padrão: base web (seed). Firebird não roda no open da tela.
        if (! Cfop::query()->exists()) {
            CfopSeeder::seedFromJson();
        }
    }

    /**
     * Sincronização opcional/manual a partir do Firebird (não usada no mount).
     */
    public function importCfopsFromFirebird(bool $force = false): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'cfops.update')) {
            return;
        }

        $result = app(CfopFirebirdSync::class)->ensureImported($force);

        if (($result['imported'] ?? false) && (($result['created'] ?? 0) + ($result['updated'] ?? 0)) > 0) {
            Notification::make()
                ->title('CFOPs importados do Firebird.')
                ->body(($result['created'] ?? 0).' novos, '.($result['updated'] ?? 0).' atualizados.')
                ->success()
                ->send();
            $this->resetTable();

            return;
        }

        if (! empty($result['message'])) {
            Notification::make()
                ->title('Não foi possível importar CFOPs do Firebird.')
                ->body((string) $result['message'])
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Nenhum CFOP novo no Firebird.')
            ->body('A base web já possui registros. Use force se quiser reimportar.')
            ->info()
            ->send();
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-cfop-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um CFOP';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-unidades__input';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createCfop';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editCfop';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'modulePending';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-unidades__input',
            'create' => 'createCfop',
            'edit' => 'editCfop',
            'delete' => null,
            'refresh' => null,
            'extraKeys' => [
                'F5' => ['method' => 'saveCfop'],
                'F6' => ['method' => 'focusSearch'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(CfopResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if ($this->statusFilter === 'ativos') {
            $query->ativos();
        } elseif ($this->statusFilter === 'inativos') {
            $query->inativos();
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, ['codigo', 'descricao'], true)
            ? $this->searchColumn
            : 'codigo';

        $like = '%'.$term.'%';

        if ($column === 'codigo') {
            $query->where(function (Builder $q) use ($like, $term): void {
                $q->where('codigo', 'like', $like);
                if (is_numeric($term)) {
                    $q->orWhere('codigo', (int) $term);
                }
            });

            return;
        }

        $query->where('descricao', 'like', $like);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.cfops.titlebar'),
                View::make('filament.components.erp.cfops.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.cfops.status-tabs'),
                View::make('filament.components.erp.cfops.action-bar'),
                View::make('filament.components.erp.cfops.modal'),
            ]);
    }

    public function setStatusFilter(string $filter): void
    {
        if (! in_array($filter, ['todos', 'ativos', 'inativos'], true)) {
            return;
        }

        $this->statusFilter = $filter;
        $this->clearListSelection();
        $this->resetTable();
    }

    public function focusSearch(): void
    {
        $this->dispatch('erp-focus-cfop-search');
    }

    public function handleCfopEscape(): void
    {
        if ($this->showForm) {
            $this->closeForm();

            return;
        }

        $this->closeScreen();
    }

    public function createCfop(): void
    {
        if ($this->showForm) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'cfops.create')) {
            return;
        }

        $this->resetForm();
        $this->form['codigo'] = (string) ((int) (Cfop::query()->max('codigo') ?? 0) + 1);
        $this->showForm = true;
    }

    public function editCfop(): void
    {
        if ($this->showForm) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'cfops.update')) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $record = Cfop::query()->find($recordId);

        if (! $record) {
            return;
        }

        $this->formId = $record->id;
        $this->form = [
            'codigo' => (string) $record->codigo,
            'descricao' => (string) $record->descricao,
            'tipo' => $record->tipo ?: Cfop::TIPO_ENTRADA,
            'operacao' => $record->operacao ?: Cfop::OPERACAO_INTERNA,
            'movimenta_estoque' => (bool) $record->movimenta_estoque,
            'ativo' => (bool) $record->ativo,
        ];
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->formId = null;
        $this->resetForm();
        $this->resetErrorBag();
    }

    public function saveCfop(): void
    {
        if (! $this->showForm) {
            return;
        }

        $permission = $this->formId ? 'cfops.update' : 'cfops.create';

        if (! ErpAccess::authorizeOrNotify(Auth::user(), $permission)) {
            return;
        }

        $data = [
            'codigo' => (int) ($this->form['codigo'] ?? 0),
            'descricao' => mb_strtoupper(trim((string) ($this->form['descricao'] ?? '')), 'UTF-8'),
            'tipo' => (string) ($this->form['tipo'] ?? Cfop::TIPO_ENTRADA),
            'operacao' => (string) ($this->form['operacao'] ?? Cfop::OPERACAO_INTERNA),
            'movimenta_estoque' => (bool) ($this->form['movimenta_estoque'] ?? false),
            'ativo' => (bool) ($this->form['ativo'] ?? false),
        ];

        $this->validate([
            'form.codigo' => [
                'required',
                'integer',
                'min:1',
                'max:9999',
                Rule::unique((new Cfop)->getTable(), 'codigo')->ignore($this->formId),
            ],
            'form.descricao' => ['required', 'string', 'max:150'],
            'form.tipo' => ['required', Rule::in(array_keys(Cfop::tipoLabels()))],
            'form.operacao' => ['required', Rule::in(array_keys(Cfop::operacaoLabels()))],
        ], [], [
            'form.codigo' => 'código',
            'form.descricao' => 'nome',
            'form.tipo' => 'tipo',
            'form.operacao' => 'operação',
        ]);

        if ($this->formId) {
            $record = Cfop::query()->find($this->formId);

            if (! $record) {
                Notification::make()->title('CFOP não encontrado.')->warning()->send();

                return;
            }

            $record->update($data);

            Notification::make()->title('CFOP alterado.')->success()->send();
        } else {
            $record = Cfop::query()->create($data);

            Notification::make()->title('CFOP incluído.')->success()->send();
        }

        $this->closeForm();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord((int) $record->id);
    }

    protected function resetForm(): void
    {
        $this->form = [
            'codigo' => null,
            'descricao' => '',
            'tipo' => Cfop::TIPO_ENTRADA,
            'operacao' => Cfop::OPERACAO_INTERNA,
            'movimenta_estoque' => true,
            'ativo' => true,
        ];
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }
}
