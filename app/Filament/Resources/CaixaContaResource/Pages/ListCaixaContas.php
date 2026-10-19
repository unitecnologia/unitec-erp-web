<?php

namespace App\Filament\Resources\CaixaContaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\CaixaContaResource;
use App\Models\CaixaConta;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Financeiro\CaixaContaExclusaoGuard;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

class ListCaixaContas extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use NormalizesErpUppercaseFormData;

    protected static string $resource = CaixaContaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public bool $showForm = false;

    public ?int $formId = null;

    public bool $deleteConfirmOpen = false;

    public ?int $deleteConfirmRecordId = null;

    public string $deleteConfirmLabel = '';

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        parent::mount();

        CaixaConta::ensureCaixaGeral();
        ErpScreen::set('Contas');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-contas-caixa-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma conta caixa';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-contas-caixa__input';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createContaCaixa';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editContaCaixa';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteContaCaixa';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            ...$this->buildSimpleListKeyboardConfig(),
            'refresh' => null,
            'blockingSelector' => '.erp-contas-caixa-modal, .erp-plano-conta-confirm',
            'extraKeys' => [
                'F4' => ['method' => 'deleteContaCaixa'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(CaixaContaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->with('ultimoUsuario');

        if (filled($this->localSearch)) {
            $this->applySimpleLocalSearch($query, $this->localSearch, ['codigo', 'nome'], 'codigo');
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.contas-caixa.titlebar'),
                View::make('filament.components.erp.contas-caixa.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.contas-caixa.action-bar'),
                View::make('filament.components.erp.contas-caixa.modal'),
                View::make('filament.components.erp.contas-caixa.confirm-delete-modal'),
            ]);
    }

    public function handleContasCaixaEscape(): void
    {
        if ($this->deleteConfirmOpen) {
            $this->cancelDeleteContaCaixa();

            return;
        }

        if ($this->showForm) {
            $this->closeForm();

            return;
        }

        $this->closeScreen();
    }

    public function createContaCaixa(): void
    {
        if ($this->showForm || $this->deleteConfirmOpen) {
            return;
        }

        $this->resetForm();
        $this->form['codigo'] = (int) (CaixaConta::max('codigo') ?? 0) + 1;
        $this->showForm = true;
    }

    public function editContaCaixa(): void
    {
        if ($this->showForm || $this->deleteConfirmOpen) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $record = CaixaConta::find($recordId);

        if (! $record) {
            return;
        }

        if ($record->isSistema()) {
            Notification::make()
                ->title('Conta do sistema.')
                ->body('O CAIXA GERAL é protegido (fechamento do PDV) e não pode ser alterado.')
                ->warning()
                ->send();

            return;
        }

        $this->formId = $record->id;
        $this->form = [
            'codigo' => $record->codigo,
            'nome' => $record->nome,
            'tipo' => match ((string) $record->tipo) {
                'CAIXA', 'X' => CaixaConta::TIPO_PDV,
                default => $record->tipo,
            },
            'ativo' => $record->ativo,
        ];
        $this->showForm = true;
    }

    public function saveContaCaixa(): void
    {
        if ($this->deleteConfirmOpen) {
            return;
        }

        if ($this->formId) {
            $existing = CaixaConta::find($this->formId);

            if ($existing?->isSistema()) {
                Notification::make()
                    ->title('Conta do sistema não pode ser alterada.')
                    ->warning()
                    ->send();

                $this->closeForm();

                return;
            }
        }

        $data = $this->validate([
            'form.codigo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('caixa_contas', 'codigo')->ignore($this->formId),
            ],
            'form.nome' => ['required', 'string', 'max:120'],
            'form.tipo' => ['required', Rule::in(CaixaConta::tiposValidos())],
        ], [], [
            'form.codigo' => 'código',
            'form.nome' => 'descrição',
            'form.tipo' => 'tipo',
        ])['form'];

        $nome = mb_strtoupper(trim($data['nome']), 'UTF-8');

        if ($nome === CaixaConta::NOME_CAIXA_GERAL) {
            Notification::make()
                ->title('Nome reservado.')
                ->body('CAIXA GERAL é uma conta do sistema e não pode ser recriada.')
                ->warning()
                ->send();

            return;
        }

        $payload = [
            'codigo' => (int) $data['codigo'],
            'nome' => $nome,
            'tipo' => $data['tipo'],
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => (bool) ($this->form['ativo'] ?? true),
            'sistema' => false,
            'ultimo_usuario_id' => Auth::id(),
        ];

        if ($this->formId) {
            CaixaConta::whereKey($this->formId)->update($payload);
        } else {
            CaixaConta::create($payload);
        }

        $this->closeForm();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Conta caixa gravada.')
            ->success()
            ->send();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function deleteContaCaixa(): void
    {
        if ($this->showForm || $this->deleteConfirmOpen) {
            return;
        }

        if (! erp_can('contas_caixa.delete')) {
            Notification::make()
                ->title('Sem permissão para excluir contas caixa.')
                ->warning()
                ->send();

            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $record = CaixaConta::query()->find($recordId);

        if (! $record) {
            Notification::make()
                ->title('Conta caixa não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $motivo = CaixaContaExclusaoGuard::motivoBloqueio($record);

        if ($motivo !== null) {
            Notification::make()
                ->title('Exclusão não permitida.')
                ->body($motivo)
                ->warning()
                ->send();

            return;
        }

        $this->deleteConfirmRecordId = (int) $record->id;
        $this->deleteConfirmLabel = trim(
            (string) $record->codigo.' — '.mb_strtoupper((string) $record->nome, 'UTF-8')
        );
        $this->deleteConfirmOpen = true;
    }

    public function confirmDeleteContaCaixa(): void
    {
        if (! $this->deleteConfirmRecordId) {
            $this->cancelDeleteContaCaixa();

            return;
        }

        if (! erp_can('contas_caixa.delete')) {
            $this->cancelDeleteContaCaixa();
            Notification::make()
                ->title('Sem permissão para excluir contas caixa.')
                ->warning()
                ->send();

            return;
        }

        try {
            DB::transaction(function (): void {
                $conta = CaixaConta::query()
                    ->whereKey($this->deleteConfirmRecordId)
                    ->lockForUpdate()
                    ->first();

                if (! $conta) {
                    throw new \RuntimeException('Conta caixa não encontrada.');
                }

                $motivo = CaixaContaExclusaoGuard::motivoBloqueio($conta);

                if ($motivo !== null) {
                    throw new \DomainException($motivo);
                }

                $conta->delete();
            });
        } catch (\DomainException $e) {
            $this->cancelDeleteContaCaixa();
            Notification::make()
                ->title('Exclusão não permitida.')
                ->body($e->getMessage())
                ->warning()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->cancelDeleteContaCaixa();
            Notification::make()
                ->title('Não foi possível excluir a conta caixa.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->cancelDeleteContaCaixa();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Conta caixa excluída.')
            ->success()
            ->send();
    }

    public function cancelDeleteContaCaixa(): void
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
            'nome' => '',
            'tipo' => CaixaConta::TIPO_PDV,
            'ativo' => true,
        ];
    }
}
