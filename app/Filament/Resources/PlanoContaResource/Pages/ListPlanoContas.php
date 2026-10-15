<?php

namespace App\Filament\Resources\PlanoContaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\PlanoContaResource;
use App\Models\PlanoConta;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Financeiro\PlanoContaExclusaoGuard;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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

    public bool $deleteConfirmOpen = false;

    public ?int $deleteConfirmRecordId = null;

    public string $deleteConfirmLabel = '';

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
            'blockingSelector' => '.erp-contas-caixa-modal, .erp-plano-conta-confirm',
            'extraKeys' => [
                'F4' => ['method' => 'deletePlanoConta'],
            ],
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
                View::make('filament.components.erp.planos-contas.confirm-delete-modal'),
            ]);
    }

    public function createPlanoConta(): void
    {
        if ($this->showForm || $this->deleteConfirmOpen) {
            return;
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function editPlanoConta(): void
    {
        if ($this->showForm || $this->deleteConfirmOpen) {
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
        if ($this->deleteConfirmOpen) {
            return;
        }

        $data = $this->validate([
            'form.conta_completa' => [
                'required',
                'string',
                'max:80',
                'regex:/^[0-9]+(\.[0-9]+)*$/',
                Rule::unique('planos_contas', 'conta_completa')->ignore($this->formId),
            ],
            'form.descricao' => ['required', 'string', 'max:120'],
            'form.dc' => ['required', Rule::in(['D', 'C'])],
        ], [
            'form.conta_completa.required' => 'Informe a conta completa.',
            'form.conta_completa.regex' => 'Use números separados por ponto (ex.: 1.01 ou 2.15).',
            'form.conta_completa.unique' => 'Esta conta completa já está cadastrada.',
            'form.dc.required' => 'Selecione Crédito ou Débito.',
        ], [
            'form.conta_completa' => 'conta completa',
            'form.descricao' => 'descrição',
            'form.dc' => 'tipo',
        ])['form'];

        $descricao = mb_strtoupper(trim((string) $data['descricao']), 'UTF-8');
        $contaCompleta = mb_strtoupper(trim((string) $data['conta_completa']), 'UTF-8');
        $dc = strtoupper((string) $data['dc']);

        if ($descricao === '') {
            $this->addError('form.descricao', 'Informe a descrição.');

            return;
        }

        if ($contaCompleta === '') {
            $this->addError('form.conta_completa', 'Informe a conta completa.');

            return;
        }

        // codigo interno ≠ conta_completa: em alteração o código permanece; no novo é gerado.
        $payload = [
            'conta_completa' => $contaCompleta,
            'descricao' => $descricao,
            'dc' => $dc,
        ];

        try {
            if ($this->formId) {
                PlanoConta::query()->whereKey($this->formId)->update($payload);
            } else {
                $this->criarComCodigoGerado($payload);
            }
        } catch (UniqueConstraintViolationException $e) {
            $msg = mb_strtolower($e->getMessage(), 'UTF-8');

            if (str_contains($msg, 'conta_completa')) {
                $this->addError('form.conta_completa', 'Esta conta completa já está cadastrada.');
            } else {
                Notification::make()
                    ->title('Não foi possível gerar o código. Tente gravar novamente.')
                    ->danger()
                    ->send();
            }

            return;
        }

        $this->closeForm();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Plano de contas gravado.')
            ->success()
            ->send();
    }

    /**
     * Aloca MAX(codigo)+1 e grava na mesma transação (lockForUpdate), com retry se colidir.
     *
     * @param  array{conta_completa: string, descricao: string, dc: string}  $payload
     */
    protected function criarComCodigoGerado(array $payload): void
    {
        $tentativas = 0;

        while ($tentativas < 5) {
            $tentativas++;

            try {
                DB::transaction(function () use ($payload): void {
                    $max = PlanoConta::query()
                        ->orderByDesc('codigo')
                        ->lockForUpdate()
                        ->value('codigo');

                    PlanoConta::query()->create([
                        ...$payload,
                        'codigo' => max(1, ((int) $max) + 1),
                        'ativo' => true,
                    ]);
                });

                return;
            } catch (UniqueConstraintViolationException $e) {
                if ($tentativas >= 5 || ! str_contains(mb_strtolower($e->getMessage(), 'UTF-8'), 'codigo')) {
                    throw $e;
                }
            }
        }
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function deletePlanoConta(): void
    {
        if ($this->showForm || $this->deleteConfirmOpen) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $record = PlanoConta::query()->find($recordId);

        if (! $record) {
            Notification::make()
                ->title('Plano de contas não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $motivo = PlanoContaExclusaoGuard::motivoBloqueio($record);

        if ($motivo !== null) {
            Notification::make()
                ->title('Exclusão não permitida.')
                ->body($motivo)
                ->warning()
                ->send();

            return;
        }

        $conta = trim((string) ($record->conta_completa ?? ''));
        $this->deleteConfirmRecordId = (int) $record->id;
        $this->deleteConfirmLabel = trim(
            ($conta !== '' ? $conta.' — ' : (string) $record->codigo.' — ')
            .mb_strtoupper((string) $record->descricao, 'UTF-8')
        );
        $this->deleteConfirmOpen = true;
    }

    public function confirmDeletePlanoConta(): void
    {
        if (! $this->deleteConfirmRecordId) {
            $this->cancelDeletePlanoConta();

            return;
        }

        try {
            DB::transaction(function (): void {
                $plano = PlanoConta::query()
                    ->whereKey($this->deleteConfirmRecordId)
                    ->lockForUpdate()
                    ->first();

                if (! $plano) {
                    throw new \RuntimeException('Plano de contas não encontrado.');
                }

                $motivo = PlanoContaExclusaoGuard::motivoBloqueio($plano);

                if ($motivo !== null) {
                    throw new \DomainException($motivo);
                }

                $plano->delete();
            });
        } catch (\DomainException $e) {
            $this->cancelDeletePlanoConta();
            Notification::make()
                ->title('Exclusão não permitida.')
                ->body($e->getMessage())
                ->warning()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->cancelDeletePlanoConta();
            Notification::make()
                ->title('Não foi possível excluir o plano de contas.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->cancelDeletePlanoConta();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Plano de contas excluído.')
            ->success()
            ->send();
    }

    public function cancelDeletePlanoConta(): void
    {
        $this->deleteConfirmOpen = false;
        $this->deleteConfirmRecordId = null;
        $this->deleteConfirmLabel = '';
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
