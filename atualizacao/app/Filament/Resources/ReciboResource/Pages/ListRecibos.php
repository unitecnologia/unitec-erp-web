<?php

namespace App\Filament\Resources\ReciboResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\ReciboResource;
use App\Filament\Resources\ReciboResource\Pages\Concerns\ManagesReciboEmailModal;
use App\Models\Person;
use App\Models\Product;
use App\Models\Recibo;
use App\Support\Erp\ErpMoney;
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
    use ManagesReciboEmailModal;
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

    public bool $recebiLookupOpen = false;

    /** @var array<int, array{id: int, nome: string, fantasia: string, cpf_cnpj: string}> */
    public array $recebiResults = [];

    public ?int $selectedRecebiIndex = null;

    public bool $referenteLookupOpen = false;

    /** @var array<int, array{id: int, codigo: string, descricao: string}> */
    public array $referenteResults = [];

    public ?int $selectedReferenteIndex = null;

    public bool $printModalOpen = false;

    public bool $previewOverlayOpen = false;

    public ?string $previewOverlayUrl = null;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Impressão de Recibos');
        $this->resetForm();
        $this->emailModalOpen = false;
        $this->aplicarPeriodoMensalPadrao();
    }

    protected function aplicarPeriodoMensalPadrao(): void
    {
        if ($this->periodoDe !== '' || $this->periodoAte !== '') {
            return;
        }

        $hoje = ErpTimezone::toLocal();
        $inicio = $hoje->copy()->startOfMonth()->toDateString();
        $fim = $hoje->copy()->endOfMonth()->toDateString();

        $this->periodoDe = $inicio;
        $this->periodoAte = $fim;
        $this->periodoDeApplied = $inicio;
        $this->periodoAteApplied = $fim;
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
            'enviar' => 'um recibo na lista para enviar',
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
            'refresh' => null,
            'searchFocusKey' => 'F12',
            'extraKeys' => [
                'F6' => ['method' => 'openPrintModal'],
                'F9' => ['method' => 'openEmailModal'],
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
                View::make('filament.components.erp.recibos.print-modal'),
                View::make('filament.components.erp.recibos.email-modal'),
                View::make('filament.components.erp.recibos.preview-overlay'),
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

        $this->resetTable();
        $this->syncErpListSyncVersionFromStore();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    public function focusSearch(): void
    {
        $this->dispatch('erp-focus-recibos-search');
    }

    protected function erpUppercaseIgnoredProperties(): array
    {
        return [
            'form.valor',
            'form.emissao',
            'form.codigo',
        ];
    }

    public function handleRecibosEscape(): void
    {
        if ($this->previewOverlayOpen) {
            $this->closePreviewOverlay();

            return;
        }

        if ($this->emailModalOpen) {
            $this->closeEmailModal();

            return;
        }

        if ($this->printModalOpen) {
            $this->closePrintModal();

            return;
        }

        if ($this->showForm && $this->recebiLookupOpen) {
            $this->closeRecebiLookup();

            return;
        }

        if ($this->showForm && $this->referenteLookupOpen) {
            $this->closeReferenteLookup();

            return;
        }

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
            'extenso' => mb_strtoupper($record->ensureExtenso(), 'UTF-8'),
            'recebi_de' => $record->recebi_de,
            'referente_a' => $record->referente_a ?? '',
        ];
        $this->showForm = true;
    }

    public function syncExtensoFromValor(): void
    {
        $parsed = ValorPorExtenso::normalize($this->form['valor'] ?? null);

        if ($parsed !== null) {
            $this->form['valor'] = ErpMoney::formatBr($parsed);
        }

        $extenso = ValorPorExtenso::fromMoney($this->form['valor'] ?? null);
        $this->form['extenso'] = $extenso !== ''
            ? mb_strtoupper($extenso, 'UTF-8')
            : '';
    }

    public function updatedFormRecebiDe(mixed $value): void
    {
        if (! $this->showForm) {
            return;
        }

        $this->recebiLookupOpen = true;
        $this->refreshRecebiResults();
    }

    public function openRecebiLookup(): void
    {
        $this->recebiLookupOpen = true;

        if (filled(trim((string) ($this->form['recebi_de'] ?? '')))) {
            $this->refreshRecebiResults();
        }
    }

    public function refreshRecebiResults(): void
    {
        $term = trim((string) ($this->form['recebi_de'] ?? ''));

        if ($term === '') {
            $this->recebiResults = [];
            $this->selectedRecebiIndex = null;

            return;
        }

        $like = '%'.$term.'%';
        $digits = preg_replace('/\D/', '', $term) ?? '';

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true)
            ->where(function ($sub) use ($like, $digits, $term): void {
                $sub->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('cpf_cnpj', 'like', $like);

                if (strlen($digits) >= 2) {
                    $digitsLike = '%'.$digits.'%';
                    $sub->orWhereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                        [$digitsLike]
                    );
                }

                if (ctype_digit($term)) {
                    $sub->orWhere('codigo', 'like', $like);
                }
            });

        $this->recebiResults = $query
            ->orderBy('nome_razao')
            ->limit(50)
            ->get()
            ->map(fn (Person $person): array => [
                'id' => $person->id,
                'nome' => mb_strtoupper($person->nome_razao, 'UTF-8'),
                'fantasia' => mb_strtoupper((string) ($person->apelido_fantasia ?? ''), 'UTF-8'),
                'cpf_cnpj' => $person->cpf_cnpj ?? '',
            ])
            ->all();

        $this->selectedRecebiIndex = $this->recebiResults === [] ? null : 0;
    }

    public function moveRecebiSelection(int $delta): void
    {
        if ($this->recebiResults === []) {
            return;
        }

        $step = $delta >= 0 ? 1 : -1;
        $index = ($this->selectedRecebiIndex ?? 0) + $step;
        $count = count($this->recebiResults);
        $this->selectedRecebiIndex = max(0, min($count - 1, $index));
    }

    public function selectRecebiResult(int $index): void
    {
        if (! isset($this->recebiResults[$index])) {
            return;
        }

        $this->selectedRecebiIndex = $index;
        $this->confirmRecebiSelection();
    }

    public function confirmRecebiSelection(): void
    {
        $index = $this->selectedRecebiIndex;

        if ($index === null || ! isset($this->recebiResults[$index])) {
            $this->closeRecebiLookup();

            return;
        }

        $this->form['recebi_de'] = $this->recebiResults[$index]['nome'];
        $this->closeRecebiLookup();
        $this->dispatch('erp-recibo-focus-referente');
    }

    public function handleRecebiEnter(): void
    {
        if ($this->recebiLookupOpen && $this->selectedRecebiIndex !== null && isset($this->recebiResults[$this->selectedRecebiIndex])) {
            $this->confirmRecebiSelection();

            return;
        }

        // Nome livre: grava no recibo mesmo sem existir no cadastro.
        $this->closeRecebiLookup();
        $this->dispatch('erp-recibo-focus-referente');
    }

    public function closeRecebiLookup(): void
    {
        $this->recebiLookupOpen = false;
        $this->recebiResults = [];
        $this->selectedRecebiIndex = null;
    }

    public function updatedFormReferenteA(mixed $value): void
    {
        if (! $this->showForm) {
            return;
        }

        $this->referenteLookupOpen = true;
        $this->refreshReferenteResults();
    }

    public function openReferenteLookup(): void
    {
        $this->referenteLookupOpen = true;

        if ($this->referenteSearchTerm() !== '') {
            $this->refreshReferenteResults();
        }
    }

    public function refreshReferenteResults(): void
    {
        $term = $this->referenteSearchTerm();

        if (mb_strlen($term) < 2) {
            $this->referenteResults = [];
            $this->selectedReferenteIndex = null;

            return;
        }

        $like = '%'.$term.'%';

        $this->referenteResults = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($like, $term): void {
                $query->where('descricao', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('codigo_barras_caixa', 'like', $like);

                if (ctype_digit($term)) {
                    $query->orWhere('codigo', $term);
                }
            })
            ->orderByRaw(
                'CASE WHEN codigo = ? THEN 0 WHEN codigo_barras = ? OR codigo_barras_caixa = ? OR referencia = ? THEN 1 WHEN descricao LIKE ? THEN 2 ELSE 3 END',
                [$term, $term, $term, $term, $term.'%'],
            )
            ->orderBy('descricao')
            ->limit(40)
            ->get(['id', 'codigo', 'descricao'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'codigo' => mb_strtoupper(trim((string) ($product->codigo ?? '')), 'UTF-8'),
                'descricao' => mb_strtoupper(trim((string) ($product->descricao ?? '')), 'UTF-8'),
            ])
            ->all();

        $this->selectedReferenteIndex = $this->referenteResults === [] ? null : 0;
    }

    public function selectReferenteResult(int $index): void
    {
        if (! isset($this->referenteResults[$index])) {
            return;
        }

        $this->selectedReferenteIndex = $index;
        $this->confirmReferenteSelection();
    }

    public function confirmReferenteSelection(): void
    {
        $index = $this->selectedReferenteIndex;

        if ($index === null || ! isset($this->referenteResults[$index])) {
            $this->closeReferenteLookup();

            return;
        }

        $descricao = $this->referenteResults[$index]['descricao'];
        $this->applyReferenteProductDescription($descricao);
        $this->closeReferenteLookup();
    }

    public function handleReferenteEnter(): void
    {
        if ($this->referenteLookupOpen && $this->selectedReferenteIndex !== null && isset($this->referenteResults[$this->selectedReferenteIndex])) {
            $this->confirmReferenteSelection();
        }
    }

    public function closeReferenteLookup(): void
    {
        $this->referenteLookupOpen = false;
        $this->referenteResults = [];
        $this->selectedReferenteIndex = null;
    }

    /**
     * Busca pela última linha digitada (permite vários produtos / textos no campo).
     */
    protected function referenteSearchTerm(): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) ($this->form['referente_a'] ?? ''));
        $lines = explode("\n", $text);
        $last = (string) end($lines);

        return trim($last);
    }

    protected function applyReferenteProductDescription(string $descricao): void
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) ($this->form['referente_a'] ?? ''));
        $lines = explode("\n", $text);

        if ($lines === []) {
            $lines = [''];
        }

        $lines[count($lines) - 1] = $descricao;
        $this->form['referente_a'] = implode("\n", $lines);
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

        $extenso = mb_strtoupper($extenso, 'UTF-8');

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
        $this->closeRecebiLookup();
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

    public function openPrintModal(): void
    {
        if ($this->showForm) {
            return;
        }

        if (! $this->erpAuthorizeOrNotify('recibos.print')) {
            return;
        }

        if (! $this->highlightedRecordIdOrNotify('print')) {
            return;
        }

        $this->printModalOpen = true;
    }

    public function closePrintModal(): void
    {
        $this->printModalOpen = false;
    }

    public function visualizarReciboImpressao(): void
    {
        if (! $this->highlightedRecordId) {
            return;
        }

        if (! $this->erpAuthorizeOrNotify('recibos.print')) {
            return;
        }

        $this->closePrintModal();
        $this->previewOverlayUrl = route('erp.reports.recibo', [
            'recibo' => $this->highlightedRecordId,
            'embed' => 1,
        ]);
        $this->previewOverlayOpen = true;
    }

    public function imprimirBobinaRecibo(): void
    {
        if (! $this->highlightedRecordId) {
            return;
        }

        if (! $this->erpAuthorizeOrNotify('recibos.print')) {
            return;
        }

        $this->closePrintModal();
        $this->previewOverlayUrl = route('erp.reports.recibo', [
            'recibo' => $this->highlightedRecordId,
            'bobina' => 1,
            'embed' => 1,
        ]);
        $this->previewOverlayOpen = true;
    }

    public function closePreviewOverlay(): void
    {
        $this->previewOverlayOpen = false;
        $this->previewOverlayUrl = null;
    }

    public function imprimirRecibo(): void
    {
        $this->openPrintModal();
    }

    protected function resetForm(): void
    {
        $this->formId = null;
        $this->closeRecebiLookup();
        $this->closeReferenteLookup();
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
