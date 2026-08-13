<?php

namespace App\Filament\Concerns;

use App\Models\Person;

trait InteractsWithLocalClienteSearchLookup
{
    public bool $localClienteLookupOpen = false;

    /** @var array<int, array{id: int, nome: string, fantasia: string, cpf_cnpj: string}> */
    public array $localClienteResults = [];

    public ?int $selectedLocalClienteIndex = null;

    public function isLocalClienteSearchColumn(): bool
    {
        $active = property_exists($this, 'searchFieldsActive') && is_array($this->searchFieldsActive)
            ? $this->searchFieldsActive
            : [];

        return in_array('cliente', $active, true)
            || ($this->searchColumn ?? null) === 'cliente';
    }

    public function updatedLocalSearch(string $value): void
    {
        $this->onLocalSearchChanged($value);

        if (! $this->isLocalClienteSearchColumn() || $this->usesLocalSearchByField()) {
            if (! $this->isLocalClienteSearchColumn()) {
                $this->closeLocalClienteLookup();
            }

            $this->clearListSelection();
            $this->resetTable();

            return;
        }

        $this->onLocalClienteSearchTyped($value);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function openLocalClienteLookup(): void
    {
        if (! $this->isLocalClienteSearchColumn()) {
            return;
        }

        $this->localClienteLookupOpen = true;

        if (filled(trim($this->localClienteSearchTerm()))) {
            $this->refreshLocalClienteResults();
        }
    }

    public function refreshLocalClienteResults(): void
    {
        $term = trim($this->localClienteSearchTerm());

        if ($term === '') {
            $this->localClienteResults = [];
            $this->selectedLocalClienteIndex = null;

            return;
        }

        $this->localClienteResults = $this->searchClientesByTerm($term);
        $this->selectedLocalClienteIndex = $this->localClienteResults === [] ? null : 0;
    }

    /**
     * @return array<int, array{id: int, nome: string, fantasia: string, cpf_cnpj: string}>
     */
    protected function searchClientesByTerm(string $term): array
    {
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

        return $query
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
    }

    public function moveLocalClienteSelection(int $delta): void
    {
        if ($this->localClienteResults === []) {
            return;
        }

        $index = ($this->selectedLocalClienteIndex ?? 0) + $delta;
        $count = count($this->localClienteResults);
        $this->selectedLocalClienteIndex = max(0, min($count - 1, $index));
    }

    public function highlightLocalClienteResult(int $index): void
    {
        if (! isset($this->localClienteResults[$index])) {
            return;
        }

        $this->selectedLocalClienteIndex = $index;
    }

    public function selectLocalClienteResult(int $index): void
    {
        if (! isset($this->localClienteResults[$index])) {
            return;
        }

        $this->selectedLocalClienteIndex = $index;
        $this->confirmLocalClienteSelection();
    }

    public function confirmLocalClienteSelection(): void
    {
        $index = $this->selectedLocalClienteIndex;

        if ($index === null || ! isset($this->localClienteResults[$index])) {
            $this->localClienteLookupOpen = false;

            return;
        }

        $row = $this->localClienteResults[$index];
        $person = Person::query()->find($row['id']);

        if (! $person) {
            return;
        }

        $this->setLocalClienteSearchTerm(mb_strtoupper($person->nome_razao, 'UTF-8'));
        $this->setLocalClienteFilter((string) $person->id);
        $this->onLocalClienteConfirmed($person);
        $this->localClienteLookupOpen = false;
        $this->localClienteResults = [];
        $this->selectedLocalClienteIndex = null;
        $this->clearListSelection();
        $this->resetTable();
    }

    public function handleLocalClienteEnter(): void
    {
        if (! $this->isLocalClienteSearchColumn()) {
            return;
        }

        if (trim($this->localClienteSearchTerm()) === '') {
            $this->setLocalClienteFilter('todos');
            $this->closeLocalClienteLookup();
            $this->clearListSelection();
            $this->resetTable();

            return;
        }

        if ($this->localClienteLookupOpen) {
            if ($this->localClienteResults === []) {
                $this->localClienteLookupOpen = false;
                $this->clearListSelection();
                $this->resetTable();

                return;
            }

            $this->confirmLocalClienteSelection();
        }
    }

    public function closeLocalClienteLookup(): void
    {
        $this->localClienteLookupOpen = false;
        $this->localClienteResults = [];
        $this->selectedLocalClienteIndex = null;
    }

    protected function shouldSkipLocalSearchWhileTyping(): bool
    {
        return $this->isLocalClienteSearchColumn()
            && $this->localClienteLookupOpen
            && filled(trim($this->localClienteSearchTerm()))
            && $this->localClienteFilter() === 'todos';
    }

    protected function onLocalClienteSearchTyped(string $value): void
    {
        if (! $this->isLocalClienteSearchColumn()) {
            $this->closeLocalClienteLookup();

            return;
        }

        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->localClienteSearchTerm() !== $upper) {
            $this->setLocalClienteSearchTerm($upper);
        }

        $this->setLocalClienteFilter('todos');
        $this->localClienteLookupOpen = true;
        $this->refreshLocalClienteResults();
    }

    protected function localClienteFilter(): string
    {
        if (property_exists($this, 'clienteFilter')) {
            return (string) $this->clienteFilter;
        }

        return 'todos';
    }

    protected function setLocalClienteFilter(string $value): void
    {
        if (property_exists($this, 'clienteFilter')) {
            $this->clienteFilter = $value;
        }
    }

    protected function localClienteSearchTerm(): string
    {
        if ($this->usesLocalSearchByField()) {
            return (string) ($this->localSearchByField['cliente'] ?? '');
        }

        return (string) ($this->localSearch ?? '');
    }

    protected function setLocalClienteSearchTerm(string $value): void
    {
        if ($this->usesLocalSearchByField()) {
            $this->localSearchByField['cliente'] = $value;

            return;
        }

        $this->localSearch = $value;
    }

    protected function usesLocalSearchByField(): bool
    {
        return property_exists($this, 'localSearchByField') && is_array($this->localSearchByField);
    }

    protected function onLocalSearchChanged(string $value): void
    {
    }

    protected function onLocalClienteConfirmed(Person $person): void
    {
    }
}
