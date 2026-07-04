<?php

namespace App\Filament\Resources\CaixaContaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\CaixaContaResource;
use App\Models\CaixaConta;
use App\Models\FormaPagamento;
use App\Support\Erp\ErpScreen;
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

class ListCaixaContas extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;

    protected static string $resource = CaixaContaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public bool $showForm = false;

    public ?int $formId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        parent::mount();

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
        return '.erp-unidades__input';
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
        return $this->buildSimpleListKeyboardConfig();
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
                View::make('filament.components.erp.contas-caixa.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.contas-caixa.action-bar'),
                View::make('filament.components.erp.contas-caixa.modal'),
            ]);
    }

    public function createContaCaixa(): void
    {
        if ($this->showForm) {
            return;
        }

        $this->resetForm();
        $this->form['codigo'] = (int) (CaixaConta::max('codigo') ?? 0) + 1;
        $this->showForm = true;
    }

    public function editContaCaixa(): void
    {
        if ($this->showForm) {
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

        $this->formId = $record->id;
        $this->form = [
            'codigo' => $record->codigo,
            'nome' => $record->nome,
            'tipo' => $record->tipo,
            'situacao' => $record->situacao,
            'ativo' => $record->ativo,
        ];
        $this->showForm = true;
    }

    public function saveContaCaixa(): void
    {
        $data = $this->validate([
            'form.codigo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('caixa_contas', 'codigo')->ignore($this->formId),
            ],
            'form.nome' => ['required', 'string', 'max:120'],
            'form.tipo' => ['required', Rule::in(CaixaConta::tiposValidos())],
            'form.situacao' => ['required', Rule::in(array_keys(CaixaConta::situacaoLabels()))],
        ], [], [
            'form.codigo' => 'código',
            'form.nome' => 'descrição',
            'form.tipo' => 'tipo',
            'form.situacao' => 'situação',
        ])['form'];

        $payload = [
            'codigo' => (int) $data['codigo'],
            'nome' => mb_strtoupper(trim($data['nome']), 'UTF-8'),
            'tipo' => $data['tipo'],
            'situacao' => $data['situacao'],
            'ativo' => (bool) ($this->form['ativo'] ?? true),
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
        if ($this->showForm) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $record = CaixaConta::find($recordId);

        if (! $record) {
            return;
        }

        if ($record->lancamentos()->exists()) {
            Notification::make()
                ->title('Conta possui lançamentos no livro caixa e não pode ser excluída.')
                ->danger()
                ->send();

            return;
        }

        if (FormaPagamento::query()->where('conta_destino_id', $record->id)->exists()) {
            Notification::make()
                ->title('Conta vinculada a forma de pagamento e não pode ser excluída.')
                ->danger()
                ->send();

            return;
        }

        $record->delete();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Conta caixa excluída.')
            ->success()
            ->send();
    }

    protected function resetForm(): void
    {
        $this->formId = null;
        $this->resetErrorBag();
        $this->form = [
            'codigo' => null,
            'nome' => '',
            'tipo' => CaixaConta::TIPO_CAIXA,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
        ];
    }
}
