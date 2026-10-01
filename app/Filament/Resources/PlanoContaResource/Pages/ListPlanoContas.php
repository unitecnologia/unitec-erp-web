<?php

namespace App\Filament\Resources\PlanoContaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\PlanoContaResource;
use App\Models\PlanoConta;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

class ListPlanoContas extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use NormalizesErpUppercaseFormData;

    protected static string $resource = PlanoContaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'descricao';

    public bool $showForm = false;

    public ?int $formId = null;

    /** @var array{codigo: int|null, conta_completa: string, descricao: string, dc: string} */
    public array $form = [
        'codigo' => null,
        'conta_completa' => '',
        'descricao' => '',
        'dc' => '',
    ];

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Plano de Contas');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-planos-contas-page';
    }

    /**
     * Reutiliza o layout de janela central com fundo esmaecido (mesmo padrão Contas Caixa).
     *
     * @return array<int, string>
     */
    protected function erpListExtraPageClasses(): array
    {
        return ['erp-contas-caixa-page'];
    }

    protected function erpListEntityName(): string
    {
        return 'um plano de contas';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-unidades__input';
    }

    protected function erpSimpleListDefaultSearchColumn(): string
    {
        return 'descricao';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createPlanoConta';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editPlanoConta';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deletePlanoConta';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            ...$this->buildSimpleListKeyboardConfig(),
            'delete' => null,
            'extraKeys' => [],
        ];
    }

    /**
     * @return list<string>
     */
    protected function erpUppercaseIgnoredProperties(): array
    {
        return ['form.codigo', 'form.dc'];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(PlanoContaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            $column = in_array($this->searchColumn, ['codigo', 'descricao'], true)
                ? $this->searchColumn
                : 'descricao';

            if ($column === 'codigo') {
                $query->where('codigo', 'like', '%' . preg_replace('/\D/', '', $term) . '%');
            } else {
                $query->where('descricao', 'like', '%' . $term . '%');
            }
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.planos-contas.titlebar'),
                View::make('filament.components.erp.planos-contas.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.planos-contas.action-bar'),
                View::make('filament.components.erp.planos-contas.modal'),
            ]);
    }

    public function createPlanoConta(): void
    {
        if ($this->showForm) {
            return;
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function editPlanoConta(): void
    {
        if ($this->showForm) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $record = PlanoConta::query()->find($recordId);

        if (! $record) {
            return;
        }

        $this->formId = $record->id;
        $this->form = [
            'codigo' => (int) $record->codigo,
            'conta_completa' => (string) ($record->conta_completa ?? ''),
            'descricao' => (string) $record->descricao,
            'dc' => strtoupper((string) ($record->dc ?? '')),
        ];
        $this->showForm = true;
    }

    public function savePlanoConta(): void
    {
        if (($this->form['dc'] ?? '') === '') {
            $this->form['dc'] = null;
        }

        $data = $this->validate([
            'form.codigo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('planos_contas', 'codigo')->ignore($this->formId),
            ],
            'form.conta_completa' => ['nullable', 'string', 'max:80'],
            'form.descricao' => ['required', 'string', 'max:120'],
            'form.dc' => ['nullable', Rule::in(['D', 'C'])],
        ], [], [
            'form.codigo' => 'código',
            'form.conta_completa' => 'conta completa',
            'form.descricao' => 'descrição',
            'form.dc' => 'tipo',
        ])['form'];

        $descricao = mb_strtoupper(trim((string) $data['descricao']), 'UTF-8');
        $contaCompleta = mb_strtoupper(trim((string) ($data['conta_completa'] ?? '')), 'UTF-8');

        if ($descricao === '') {
            $this->addError('form.descricao', 'Informe a descrição.');

            return;
        }

        $payload = [
            'codigo' => (int) $data['codigo'],
            'conta_completa' => $contaCompleta !== '' ? $contaCompleta : null,
            'descricao' => $descricao,
            'dc' => filled($data['dc'] ?? null) ? strtoupper((string) $data['dc']) : null,
        ];

        if ($this->formId) {
            PlanoConta::query()->whereKey($this->formId)->update($payload);
        } else {
            PlanoConta::query()->create($payload);
        }

        $this->closeForm();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Plano de contas gravado.')
            ->success()
            ->send();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->formId = null;
        $this->resetErrorBag();
        $this->form = [
            'codigo' => null,
            'conta_completa' => '',
            'descricao' => '',
            'dc' => '',
        ];
    }
}
