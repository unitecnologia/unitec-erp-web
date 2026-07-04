<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Grupo;
use App\Models\Marca;
use App\Models\Ncm;
use App\Models\Product;
use App\Models\Unidade;
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
        if ($this->lookupPanel !== 'list') {
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
        $this->lookupPanel = 'form';
        $this->lookupEditingId = null;
        $this->resetLookupForm();

        $this->dispatch('erp-lookup-form-opened');
    }

    public function startLookupEdit(): void
    {
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
        $definition = $this->currentLookupDefinition();
        $fields = $definition['formFields'];

        foreach ($fields as $field) {
            $value = trim((string) ($this->lookupForm[$field] ?? ''));

            if ($value === '') {
                Notification::make()
                    ->title('Preencha todos os campos.')
                    ->warning()
                    ->send();

                return;
            }

            if ($this->lookupType === 'ncm' && $field === 'codigo') {
                $this->lookupForm[$field] = str_pad(preg_replace('/\D/', '', $value), 8, '0', STR_PAD_LEFT);

                continue;
            }

            $this->lookupForm[$field] = Str::upper($value);
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $definition['model'];
        $uniqueField = $definition['valueColumn'];
        $uniqueValue = $this->lookupForm[$uniqueField] ?? '';

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

                $record->fill($this->lookupForm);
                $record->save();
                $this->lookupHighlightedId = $record->getKey();
            } else {
                /** @var Model $record */
                $record = $modelClass::query()->create([
                    ...$this->lookupForm,
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

        $this->lookupSearch = $uniqueValue;
        $this->lookupPanel = 'list';
        $this->lookupEditingId = null;
        $this->resetLookupForm();

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

            $query->where($column, 'like', '%' . $this->lookupSearch . '%');
        }

        return $query
            ->orderBy($definition['defaultSearchColumn'])
            ->limit(200)
            ->get()
            ->map(fn (Model $record): array => [
                'id' => $record->getKey(),
                'values' => collect($definition['columns'])
                    ->map(fn (string $label, string $key): string => (string) $record->{$key})
                    ->all(),
            ])
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

        foreach ($this->lookupDefinition($this->lookupType)['formFields'] as $field) {
            $this->lookupForm[$field] = '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentLookupDefinition(): array
    {
        if (! $this->lookupType) {
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
        $ncm = str_pad(preg_replace('/\D/', '', (string) ($this->data['ncm'] ?? '')), 8, '0', STR_PAD_LEFT);

        if (strlen($ncm) !== 8) {
            return;
        }

        $this->data['ncm'] = $ncm;

        $descricao = Ncm::query()->where('codigo', $ncm)->value('descricao');

        if ($descricao) {
            $this->data['ncm_descricao'] = $descricao;
        }

        $this->form->fill($this->data);
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
