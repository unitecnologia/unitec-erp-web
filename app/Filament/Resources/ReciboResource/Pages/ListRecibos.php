<?php

namespace App\Filament\Resources\ReciboResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\ReciboResource;
use App\Models\Recibo;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\ValorPorExtenso;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListRecibos extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use InteractsWithErpSimpleListPage;
    use NormalizesErpUppercaseFormData;

    protected static string $resource = ReciboResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public bool $showForm = false;

    public ?int $formId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Impressão de Recibos');
        $this->resetForm();
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-recibos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um recibo';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-recibos__input';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createRecibo';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editRecibo';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteRecibo';
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'print' => 'um recibo na lista para imprimir',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-recibos__input',
            'create' => 'createRecibo',
            'edit' => 'editRecibo',
            'delete' => null,
            'refresh' => 'refreshTable',
            'searchFocusKey' => 'F12',
            'extraKeys' => [
                'F5' => ['method' => 'refreshTable'],
                'F6' => ['method' => 'imprimirRecibo'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(ReciboResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->periodoDeApplied)) {
            $query->whereDate('emissao', '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate('emissao', '<=', $this->periodoAteApplied);
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

        $column = in_array($this->searchColumn, ['codigo', 'recebi_de'], true)
            ? $this->searchColumn
            : 'codigo';

        $like = '%'.$term.'%';

        if ($column === 'codigo') {
            if (is_numeric($term)) {
                $query->where('codigo', (int) $term);

                return;
            }

            if ($query->getConnection()->getDriverName() === 'sqlite') {
                $query->whereRaw('CAST(codigo AS TEXT) LIKE ?', [$like]);

                return;
            }

            $query->whereRaw('CAST(codigo AS CHAR) LIKE ?', [$like]);

            return;
        }

        $query->where('recebi_de', 'like', $like);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.recibos.titlebar'),
                View::make('filament.components.erp.recibos.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.recibos.footer-summary'),
                View::make('filament.components.erp.recibos.action-bar'),
                View::make('filament.components.erp.recibos.modal'),
            ]);
    }

    #[Computed]
    public function totalValor(): float
    {
        return (float) $this->getTableQuery()->sum('valor');
    }

    public function applyPeriodFilter(): void
    {
        $this->periodoDeApplied = trim($this->periodoDe);
        $this->periodoAteApplied = trim($this->periodoAte);
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Período filtrado.')
            ->success()
            ->send();
    }

    public function refreshTable(): void
    {
        if ($this->showForm) {
            $this->saveRecibo();

            return;
        }

        parent::refreshTable();
    }

    public function focusSearch(): void
    {
        $this->dispatch('erp-focus-recibos-search');
    }

    protected function erpUppercaseIgnoredProperties(): array
    {
        return [
            'form.valor',
            'form.extenso',
            'form.emissao',
            'form.codigo',
        ];
    }

    public function handleRecibosEscape(): void
    {
        if ($this->showForm) {
            $this->closeForm();

            return;
        }

        $this->closeScreen();
    }

    public function createRecibo(): void
    {
        if ($this->showForm) {
            return;
        }

        if (! $this->erpAuthorizeOrNotify('recibos.create')) {
            return;
        }

        $this->resetForm();
        $this->form['codigo'] = Recibo::nextCodigo();
        $this->form['emissao'] = ErpTimezone::toLocal()->toDateString();
        $this->showForm = true;
    }

    public function editRecibo(): void
    {
        if ($this->showForm) {
            return;
        }

        if (! $this->erpAuthorizeOrNotify('recibos.update')) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $record = Recibo::query()->find($recordId);

        if (! $record) {
            return;
        }

        $this->formId = $record->id;
        $this->form = [
            'codigo' => $record->codigo,
            'emissao' => $record->emissao?->format('Y-m-d') ?? '',
            'valor' => number_format((float) $record->valor, 2, ',', '.'),
            'extenso' => $record->ensureExtenso(),
            'recebi_de' => $record->recebi_de,
            'referente_a' => $record->referente_a ?? '',
        ];
        $this->showForm = true;
    }

    public function updatedFormValor(mixed $value): void
    {
        $this->form['extenso'] = ValorPorExtenso::fromMoney($value);
    }

    public function saveRecibo(): void
    {
        if (! $this->showForm) {
            return;
        }

        $permission = $this->formId ? 'recibos.update' : 'recibos.create';

        if (! $this->erpAuthorizeOrNotify($permission)) {
            return;
        }

        $data = $this->validate([
            'form.codigo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('recibos', 'codigo')->ignore($this->formId),
            ],
            'form.emissao' => ['required', 'date'],
            'form.valor' => ['required'],
            'form.extenso' => ['nullable', 'string', 'max:500'],
            'form.recebi_de' => ['required', 'string', 'max:200'],
            'form.referente_a' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'form.codigo' => 'código',
            'form.emissao' => 'emissão',
            'form.valor' => 'valor',
            'form.extenso' => 'extenso',
            'form.recebi_de' => 'recebi de',
            'form.referente_a' => 'referente a',
        ])['form'];

        $valor = ValorPorExtenso::normalize($data['valor'] ?? null);

        if ($valor === null || $valor < 0) {
            $this->addError('form.valor', 'Informe um valor válido.');

            return;
        }

        $extenso = trim((string) ($data['extenso'] ?? ''));

        if ($extenso === '') {
            $extenso = ValorPorExtenso::fromMoney($valor);
        }

        $payload = [
            'codigo' => (int) $data['codigo'],
            'emissao' => $data['emissao'],
            'valor' => $valor,
            'extenso' => $extenso,
            'recebi_de' => mb_strtoupper(trim((string) $data['recebi_de']), 'UTF-8'),
            'referente_a' => filled($data['referente_a'] ?? null)
                ? mb_strtoupper(trim((string) $data['referente_a']), 'UTF-8')
                : null,
        ];

        if ($this->formId) {
            Recibo::query()->whereKey($this->formId)->update($payload);
            $savedId = $this->formId;
        } else {
            $savedId = Recibo::query()->create($payload)->id;
        }

        $this->closeForm();
        $this->highlightedRecordId = $savedId;
        $this->resetTable();

        Notification::make()
            ->title('Recibo gravado.')
            ->success()
            ->send();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function deleteRecibo(): void
    {
        if ($this->showForm) {
            return;
        }

        if (! $this->erpAuthorizeOrNotify('recibos.delete')) {
            return;
        }

        $this->deleteSimpleRecord(Recibo::class, 'Recibo excluído.');
    }

    public function imprimirRecibo(): void
    {
        if ($this->showForm) {
            return;
        }

        if (! $this->erpAuthorizeOrNotify('recibos.print')) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('print');

        if (! $recordId) {
            return;
        }

        $this->redirect(route('erp.reports.recibo', ['recibo' => $recordId]), navigate: false);
    }

    protected function resetForm(): void
    {
        $this->formId = null;
        $this->resetErrorBag();
        $this->form = [
            'codigo' => null,
            'emissao' => ErpTimezone::toLocal()->toDateString(),
            'valor' => '',
            'extenso' => '',
            'recebi_de' => '',
            'referente_a' => '',
        ];
    }
}
