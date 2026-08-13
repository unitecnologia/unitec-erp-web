<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Grupo;
use App\Models\Marca;
use App\Models\Ncm;
use App\Models\Product;
use App\Models\Unidade;
use App\Support\Erp\Fiscal\NcmCatalogService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use RuntimeException;

trait ManagesProductCadastroLookup
{
    public bool $lookupOpen = false;

    public ?string $lookupType = null;

    public string $lookupPanel = 'list';

    public string $lookupSearchColumn = 'sigla';

    public string $lookupSearch = '';

    public ?int $lookupHighlightedId = null;

    public ?int $lookupEditingId = null;

    /** @var array<string, string> */
    public array $lookupForm = [];

    public bool $ncmConfirmOpen = false;

    public string $ncmConfirmCodigo = '';

    public string $ncmConfirmDescricao = '';

    public bool $ncmConfirmApplyToProduct = true;

    public function openProductLookup(string $type): void
    {
        $definition = $this->lookupDefinition($type);

        $this->lookupType = $type;
        $this->lookupOpen = true;
        $this->lookupPanel = 'list';
        $this->lookupSearchColumn = $definition['defaultSearchColumn'];
        $this->lookupSearch = '';
        $this->lookupHighlightedId = null;
        $this->lookupEditingId = null;
        $this->resetLookupForm();

        $currentValue = trim((string) ($this->data[$definition['targetField']] ?? ''));

        if ($currentValue !== '') {
            $record = $definition['model']::query()
                ->where('ativo', true)
                ->where($definition['valueColumn'], $currentValue)
                ->first();

            $this->lookupHighlightedId = $record?->getKey();
        }

        $this->dispatch('erp-lookup-opened', type: $type);
    }

    public function closeProductLookup(): void
    {
        $this->lookupOpen = false;
        $this->lookupType = null;
        $this->lookupPanel = 'list';
        $this->lookupSearch = '';
        $this->lookupHighlightedId = null;
        $this->lookupEditingId = null;
        $this->resetLookupForm();

        $this->dispatch('erp-lookup-closed');
    }

    public function handleLookupEscape(): void
    {
        if (! $this->lookupOpen || ! $this->lookupType) {
            return;
        }

        if ($this->lookupPanel === 'form') {
            $this->cancelLookupForm();

            return;
        }

        $this->closeProductLookup();
    }

    public function setLookupSearchColumn(string $column): void
    {
        $definition = $this->currentLookupDefinition();
        $allowed = array_keys($definition['columns']);

        if (! in_array($column, $allowed, true)) {
            return;
        }

        $this->lookupSearchColumn = $column;
        $this->lookupHighlightedId = null;
    }

    public function updatedLookupSearch(): void
    {
        $this->syncLookupHighlightFromSearch();
    }

    public function highlightLookupRecord(int $recordId): void
    {
        $this->lookupHighlightedId = $recordId;
    }

    public function confirmProductLookup(?int $recordId = null): void
    {
        if (! $this->lookupOpen || ! $this->lookupType || $this->lookupPanel !== 'list') {
            return;
        }

        $definition = $this->currentLookupDefinition();
        $recordId ??= $this->lookupHighlightedId;

        if (! $recordId) {
            Notification::make()
                ->title('Selecione um registro.')
                ->warning()
                ->send();

            return;
        }

        $record = $definition['model']::query()->find($recordId);

        if (! $record) {
            Notification::make()
                ->title('Registro não encontrado.')
                ->danger()
                ->send();

            return;
        }

        $valueColumn = $definition['valueColumn'];
        $this->data[$definition['targetField']] = (string) $record->{$valueColumn};

        foreach ($definition['extraTargetFields'] ?? [] as $formField => $recordField) {
            $this->data[$formField] = (string) $record->{$recordField};
        }

        $this->form->fill($this->data);

        $this->closeProductLookup();
    }

    public function startLookupCreate(): void
    {
        if (! $this->lookupOpen || ! $this->lookupType) {
            return;
        }

        $this->lookupPanel = 'form';
        $this->lookupEditingId = null;
        $this->resetLookupForm();

        if ($this->lookupType === 'ncm') {
            $catalog = app(NcmCatalogService::class);
            $codigo = $catalog->normalizeCodigo($this->lookupSearch)
                ?? $catalog->normalizeCodigo((string) ($this->data['ncm'] ?? ''));

            if ($codigo !== null) {
                $this->lookupForm['codigo'] = $codigo;
            }

            // Se pesquisou por texto e não achou, sugere a descrição digitada.
            $search = trim($this->lookupSearch);
            if ($search !== '' && ! ctype_digit(preg_replace('/\D/', '', $search) ?: '') && blank($this->lookupForm['descricao'] ?? null)) {
                $this->lookupForm['descricao'] = Str::upper($search);
            }
        }

        $this->dispatch('erp-lookup-form-opened');
    }

    public function startLookupEdit(): void
    {
        if (! $this->lookupOpen || ! $this->lookupType) {
            return;
        }

        if (! $this->lookupHighlightedId) {
            Notification::make()
                ->title('Selecione um registro para alterar.')
                ->warning()
                ->send();

            return;
        }

        $definition = $this->currentLookupDefinition();
        $record = $definition['model']::query()->find($this->lookupHighlightedId);

        if (! $record) {
            Notification::make()
                ->title('Registro não encontrado.')
                ->danger()
                ->send();

            return;
        }

        $this->lookupPanel = 'form';
        $this->lookupEditingId = $record->getKey();

        foreach ($definition['formFields'] as $field) {
            if ($this->lookupFieldIsBoolean($definition, $field)) {
                $this->lookupForm[$field] = (bool) $record->{$field};

                continue;
            }

            $this->lookupForm[$field] = (string) $record->{$field};
        }

        $this->dispatch('erp-lookup-form-opened');
    }

    public function cancelLookupForm(): void
    {
        $this->lookupPanel = 'list';
        $this->lookupEditingId = null;
        $this->resetLookupForm();
    }

    public function saveLookupRecord(): void
    {
        if (! $this->lookupOpen || ! $this->lookupType || $this->lookupPanel !== 'form') {
            return;
        }

        $definition = $this->currentLookupDefinition();
        $fields = $definition['formFields'];
        $payload = [];

        foreach ($fields as $field) {
            if ($this->lookupFieldIsBoolean($definition, $field)) {
                $payload[$field] = filter_var($this->lookupForm[$field] ?? false, FILTER_VALIDATE_BOOLEAN);
                $this->lookupForm[$field] = $payload[$field];

                continue;
            }

            $value = trim((string) ($this->lookupForm[$field] ?? ''));

            if ($value === '') {
                Notification::make()
                    ->title('Preencha todos os campos.')
                    ->warning()
                    ->send();

                return;
            }

            if ($this->lookupType === 'ncm' && $field === 'codigo') {
                $payload[$field] = str_pad(preg_replace('/\D/', '', $value), 8, '0', STR_PAD_LEFT);
                $this->lookupForm[$field] = $payload[$field];

                continue;
            }

            $payload[$field] = Str::upper($value);
            $this->lookupForm[$field] = $payload[$field];
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $definition['model'];
        $uniqueField = $definition['valueColumn'];
        $uniqueValue = $payload[$uniqueField] ?? '';

        if ($this->lookupRecordExists($modelClass, $uniqueField, $uniqueValue, $this->lookupEditingId)) {
            Notification::make()
                ->title('Registro já cadastrado.')
                ->body('Já existe um item com este código/descrição.')
                ->warning()
                ->send();

            return;
        }

        try {
            if ($this->lookupEditingId) {
                /** @var Model|null $record */
                $record = $modelClass::query()->find($this->lookupEditingId);

                if (! $record) {
                    Notification::make()
                        ->title('Registro não encontrado.')
                        ->danger()
                        ->send();

                    return;
                }

                $record->fill($payload);
                $record->save();
                $this->lookupHighlightedId = $record->getKey();
            } else {
                /** @var Model $record */
                $record = $modelClass::query()->create([
                    ...$payload,
                    'ativo' => true,
                ]);
                $this->lookupHighlightedId = $record->getKey();
            }
        } catch (UniqueConstraintViolationException) {
            Notification::make()
                ->title('Registro já cadastrado.')
                ->body('Já existe um item com este código/descrição.')
                ->warning()
                ->send();

            return;
        }

        $wasCreating = $this->lookupEditingId === null;
        $applyNcmToProduct = $this->lookupType === 'ncm' && $wasCreating;
        $ncmCodigo = $applyNcmToProduct ? (string) ($this->lookupForm['codigo'] ?? $uniqueValue) : '';
        $ncmDescricao = $applyNcmToProduct ? (string) ($this->lookupForm['descricao'] ?? '') : '';

        $this->lookupSearch = $uniqueValue;
        $this->lookupPanel = 'list';
        $this->lookupEditingId = null;
        $this->resetLookupForm();
        $this->ncmConfirmOpen = false;
        $this->ncmConfirmCodigo = '';
        $this->ncmConfirmDescricao = '';

        if ($applyNcmToProduct && $ncmCodigo !== '') {
            $this->applyNcmToProductForm($ncmCodigo, $ncmDescricao);
            $this->closeProductLookup();
        }

        Notification::make()
            ->title('Registro salvo.')
            ->success()
            ->send();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLookupRecordsProperty(): array
    {
        if (! $this->lookupOpen || ! $this->lookupType) {
            return [];
        }

        $definition = $this->currentLookupDefinition();
        $query = $definition['model']::query()->where('ativo', true);

        if (filled($this->lookupSearch)) {
            $column = in_array($this->lookupSearchColumn, $definition['searchColumns'], true)
                ? $this->lookupSearchColumn
                : $definition['defaultSearchColumn'];

            $search = trim($this->lookupSearch);

            if ($this->lookupType === 'ncm' && $column === 'codigo') {
                $digits = preg_replace('/\D/', '', $search) ?? '';
                if ($digits !== '') {
                    $query->where('codigo', 'like', $digits.'%');
                } else {
                    $query->where($column, 'like', '%'.$search.'%');
                }
            } else {
                $query->where($column, 'like', '%'.$search.'%');
            }
        }

        $limit = $this->lookupType === 'ncm' ? 300 : 200;

        return $query
            ->orderBy($definition['defaultSearchColumn'])
            ->limit($limit)
            ->get()
            ->map(function (Model $record) use ($definition): array {
                $values = [];

                foreach ($definition['columns'] as $key => $label) {
                    if ($this->lookupFieldIsBoolean($definition, $key)) {
                        $values[$key] = (bool) $record->{$key};

                        continue;
                    }

                    $values[$key] = (string) $record->{$key};
                }

                return [
                    'id' => $record->getKey(),
                    'values' => $values,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getLookupViewStateProperty(): array
    {
        if (! $this->lookupOpen || ! $this->lookupType) {
            return [];
        }

        $definition = $this->currentLookupDefinition();

        return [
            'type' => $this->lookupType,
            'title' => $definition['title'],
            'panel' => $this->lookupPanel,
            'searchColumn' => $this->lookupSearchColumn,
            'searchLabel' => $definition['columns'][$this->lookupSearchColumn] ?? 'Campo',
            'columns' => $definition['columns'],
            'booleanFields' => $definition['booleanFields'] ?? [],
            'searchColumns' => $definition['searchColumns'] ?? [],
            'formFields' => collect($definition['formFields'])
                ->mapWithKeys(fn (string $field): array => [
                    $field => $definition['columns'][$field] ?? Str::headline($field),
                ])
                ->all(),
            'records' => $this->lookupRecords,
            'highlightedId' => $this->lookupHighlightedId,
            'editing' => $this->lookupEditingId !== null,
        ];
    }

    protected function syncLookupHighlightFromSearch(): void
    {
        if (! filled($this->lookupSearch) || ! $this->lookupType) {
            return;
        }

        $definition = $this->currentLookupDefinition();
        $valueColumn = $definition['valueColumn'];

        $record = $definition['model']::query()
            ->where('ativo', true)
            ->where($valueColumn, $this->lookupSearch)
            ->first();

        $this->lookupHighlightedId = $record?->getKey();
    }

    protected function lookupRecordExists(string $modelClass, string $field, string $value, ?int $excludeId = null): bool
    {
        return $modelClass::query()
            ->where($field, $value)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    protected function resetLookupForm(): void
    {
        $this->lookupForm = [];

        if (! $this->lookupType) {
            return;
        }

        $definition = $this->lookupDefinition($this->lookupType);

        foreach ($definition['formFields'] as $field) {
            $this->lookupForm[$field] = $this->lookupFieldIsBoolean($definition, $field) ? false : '';
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function lookupFieldIsBoolean(array $definition, string $field): bool
    {
        return in_array($field, $definition['booleanFields'] ?? [], true);
    }

    public function toggleLookupBoolean(int $recordId, string $field): void
    {
        if (! $this->lookupOpen || ! $this->lookupType || $this->lookupPanel !== 'list') {
            return;
        }

        $definition = $this->currentLookupDefinition();

        if (! $this->lookupFieldIsBoolean($definition, $field)) {
            return;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $definition['model'];
        /** @var Model|null $record */
        $record = $modelClass::query()->find($recordId);

        if (! $record) {
            return;
        }

        $record->{$field} = ! (bool) $record->{$field};
        $record->save();
        $this->lookupHighlightedId = $record->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentLookupDefinition(): array
    {
        if (! $this->lookupOpen || ! $this->lookupType) {
            throw new RuntimeException('Lookup type not set.');
        }

        return $this->lookupDefinition($this->lookupType);
    }

    /**
     * @return array<string, mixed>
     */
    protected function lookupDefinition(string $type): array
    {
        return match ($type) {
            'unidade' => [
                'title' => 'Unidade',
                'targetField' => 'unidade',
                'valueColumn' => 'sigla',
                'model' => Unidade::class,
                'columns' => [
                    'sigla' => 'Sigla',
                    'descricao' => 'Descrição',
                ],
                'searchColumns' => ['sigla', 'descricao'],
                'defaultSearchColumn' => 'sigla',
                'formFields' => ['sigla', 'descricao'],
            ],
            'marca' => [
                'title' => 'Marca',
                'targetField' => 'marca',
                'valueColumn' => 'nome',
                'model' => Marca::class,
                'columns' => [
                    'nome' => 'Marca',
                ],
                'searchColumns' => ['nome'],
                'defaultSearchColumn' => 'nome',
                'formFields' => ['nome'],
            ],
            'grupo' => [
                'title' => 'Grupo',
                'targetField' => 'grupo',
                'valueColumn' => 'nome',
                'model' => Grupo::class,
                'columns' => [
                    'nome' => 'Grupo',
                ],
                'searchColumns' => ['nome'],
                'defaultSearchColumn' => 'nome',
                'formFields' => ['nome'],
            ],
            'ncm' => [
                'title' => 'NCM',
                'targetField' => 'ncm',
                'valueColumn' => 'codigo',
                'extraTargetFields' => [
                    'ncm_descricao' => 'descricao',
                ],
                'model' => Ncm::class,
                'columns' => [
                    'codigo' => 'Código',
                    'descricao' => 'Descrição',
                ],
                'searchColumns' => ['codigo', 'descricao'],
                'defaultSearchColumn' => 'codigo',
                'formFields' => ['codigo', 'descricao'],
            ],
            default => throw new RuntimeException('Lookup não configurado.'),
        };
    }

    public function syncNcmDescricaoFromCodigo(): void
    {
        $catalog = app(NcmCatalogService::class);
        $ncm = $catalog->normalizeCodigo((string) ($this->data['ncm'] ?? ''));

        if ($ncm === null) {
            return;
        }

        $this->data['ncm'] = $ncm;

        $record = $catalog->findByCodigo($ncm);

        if ($record) {
            $this->applyNcmToProductForm((string) $record->codigo, (string) $record->descricao);
            $this->ncmConfirmOpen = false;

            return;
        }

        // NCM digitado não existe na tabela única — pergunta se deseja cadastrar.
        $this->ncmConfirmCodigo = $ncm;
        $this->ncmConfirmDescricao = trim((string) ($this->data['ncm_descricao'] ?? ''));
        $this->ncmConfirmApplyToProduct = true;
        $this->ncmConfirmOpen = true;
        $this->data['ncm_descricao'] = '';

        if (isset($this->form) && method_exists($this->form, 'fill')) {
            $this->form->fill($this->data);
        }
    }

    /**
     * Preenche a descrição do NCM a partir do catálogo (sem abrir modal de cadastro).
     */
    public function hydrateNcmDescricaoFromCatalog(bool $fillForm = true): void
    {
        $catalog = app(NcmCatalogService::class);
        $ncm = $catalog->normalizeCodigo((string) ($this->data['ncm'] ?? ''));

        if ($ncm === null) {
            return;
        }

        $this->data['ncm'] = $ncm;

        $record = $catalog->findByCodigo($ncm);

        if (! $record) {
            if (blank($this->data['ncm_descricao'] ?? null)) {
                $this->data['ncm_descricao'] = '';
            }

            return;
        }

        $this->data['ncm_descricao'] = (string) $record->descricao;

        if ($fillForm && isset($this->form) && method_exists($this->form, 'fill')) {
            $this->form->fill($this->data);
        }
    }

    public function confirmCadastrarNcm(): void
    {
        $codigo = app(NcmCatalogService::class)->normalizeCodigo($this->ncmConfirmCodigo);

        if ($codigo === null) {
            $this->cancelCadastrarNcm();

            return;
        }

        $this->ncmConfirmOpen = false;
        $this->ncmConfirmApplyToProduct = true;
        $this->openProductLookup('ncm');
        $this->lookupSearch = $codigo;
        $this->startLookupCreate();
        $this->lookupForm['codigo'] = $codigo;
        $this->lookupForm['descricao'] = Str::upper(trim($this->ncmConfirmDescricao));
    }

    public function cancelCadastrarNcm(): void
    {
        $this->ncmConfirmOpen = false;
        // Mantém o código digitado, mas sem descrição oficial.
        $this->data['ncm'] = $this->ncmConfirmCodigo !== ''
            ? $this->ncmConfirmCodigo
            : ($this->data['ncm'] ?? '');
        $this->ncmConfirmCodigo = '';
        $this->ncmConfirmDescricao = '';

        if (isset($this->form) && method_exists($this->form, 'fill')) {
            $this->form->fill($this->data);
        }
    }

    protected function applyNcmToProductForm(string $codigo, string $descricao): void
    {
        $this->data['ncm'] = $codigo;
        $this->data['ncm_descricao'] = $descricao;

        if (isset($this->form) && method_exists($this->form, 'fill')) {
            $this->form->fill($this->data);
        }
    }

    /**
     * @return list<string>
     */
    public function getMarcaOptionsProperty(): array
    {
        return $this->productAuxiliaryOptions(Marca::class, 'nome', 'marca');
    }

    /**
     * @return list<string>
     */
    public function getGrupoOptionsProperty(): array
    {
        return $this->productAuxiliaryOptions(Grupo::class, 'nome', 'grupo');
    }

    /**
     * Linhas do select de grupo (nome + flags App / Balança).
     *
     * @return list<array{id: int, nome: string, label: string, mostrar_no_app: bool, balanca_marcado: bool}>
     */
    public function getGrupoSelectRowsProperty(): array
    {
        $rows = Grupo::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'mostrar_no_app', 'balanca_marcado'])
            ->map(fn (Grupo $grupo): array => [
                'id' => (int) $grupo->id,
                'nome' => (string) $grupo->nome,
                'label' => Grupo::displayNome((string) $grupo->nome),
                'mostrar_no_app' => (bool) $grupo->mostrar_no_app,
                'balanca_marcado' => (bool) $grupo->balanca_marcado,
            ])
            ->all();

        $current = trim((string) ($this->data['grupo'] ?? ''));

        if ($current !== '' && ! collect($rows)->contains(fn (array $row): bool => $row['nome'] === $current)) {
            array_unshift($rows, [
                'id' => 0,
                'nome' => $current,
                'label' => Grupo::displayNome($current),
                'mostrar_no_app' => false,
                'balanca_marcado' => false,
            ]);
        }

        return $rows;
    }

    public function toggleGrupoFlag(string $nome, string $field): void
    {
        if (! in_array($field, ['mostrar_no_app', 'balanca_marcado'], true)) {
            return;
        }

        $nome = trim($nome);

        if ($nome === '') {
            return;
        }

        $grupo = Grupo::query()
            ->whereRaw('UPPER(TRIM(nome)) = ?', [mb_strtoupper($nome, 'UTF-8')])
            ->first();

        if (! $grupo) {
            return;
        }

        $grupo->{$field} = ! (bool) $grupo->{$field};
        $grupo->save();
    }

    /**
     * @return array<string, string>
     */
    public function getUnidadeOptionsProperty(): array
    {
        return Product::unidades();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return list<string>
     */
    protected function productAuxiliaryOptions(string $modelClass, string $column, string $dataField): array
    {
        $options = $modelClass::query()
            ->where('ativo', true)
            ->orderBy($column)
            ->pluck($column)
            ->all();

        $current = trim((string) ($this->data[$dataField] ?? ''));

        if ($current !== '' && ! in_array($current, $options, true)) {
            array_unshift($options, $current);
        }

        return $options;
    }
}
