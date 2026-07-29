<?php

namespace App\Filament\Concerns;

use App\Models\Person;

trait InteractsWithLocalFornecedorSearchLookup
{
    public bool $localFornecedorLookupOpen = false;

    /** @var array<int, array{id: int, nome: string, fantasia: string, cpf_cnpj: string}> */
    public array $localFornecedorResults = [];

    public ?int $selectedLocalFornecedorIndex = null;

    public string $fornecedorFilter = 'todos';

    public function isLocalFornecedorSearchActive(): bool
    {
        $active = property_exists($this, 'searchFieldsActive') && is_array($this->searchFieldsActive)
            ? $this->searchFieldsActive
            : [];

        return in_array('fornecedor', $active, true)
            || ($this->searchColumn ?? null) === 'fornecedor';
    }

    public function openLocalFornecedorLookup(): void
    {
        if (! $this->isLocalFornecedorSearchActive()) {
            return;
        }

        $this->localFornecedorLookupOpen = true;

        if (filled(trim($this->localFornecedorSearchTerm()))) {
            $this->refreshLocalFornecedorResults();
        }
    }

    public function refreshLocalFornecedorResults(): void
    {
        $term = trim($this->localFornecedorSearchTerm());

        if ($term === '') {
            $this->localFornecedorResults = [];
            $this->selectedLocalFornecedorIndex = null;

            return;
        }

        $this->localFornecedorResults = $this->searchFornecedoresByTerm($term);
        $this->selectedLocalFornecedorIndex = $this->localFornecedorResults === [] ? null : 0;
    }

    /**
     * @return array<int, array{id: int, nome: string, fantasia: string, cpf_cnpj: string}>
     */
    protected function searchFornecedoresByTerm(string $term): array
    {
        $like = '%'.$term.'%';
        $digits = preg_replace('/\D/', '', $term) ?? '';

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_fornecedor', true)
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

    public function moveLocalFornecedorSelection(int $delta): void
    {
        if ($this->localFornecedorResults === []) {
            return;
        }

        $index = ($this->selectedLocalFornecedorIndex ?? 0) + $delta;
        $count = count($this->localFornecedorResults);
        $this->selectedLocalFornecedorIndex = max(0, min($count - 1, $index));
    }

    public function highlightLocalFornecedorResult(int $index): void
    {
        if (! isset($this->localFornecedorResults[$index])) {
            return;
        }

        $this->selectedLocalFornecedorIndex = $index;
    }

    public function selectLocalFornecedorResult(int $index): void
    {
        if (! isset($this->localFornecedorResults[$index])) {
            return;
        }

        $this->selectedLocalFornecedorIndex = $index;
        $this->confirmLocalFornecedorSelection();
    }

    public function confirmLocalFornecedorSelection(): void
    {
        $index = $this->selectedLocalFornecedorIndex;

        if ($index === null || ! isset($this->localFornecedorResults[$index])) {
            $this->localFornecedorLookupOpen = false;

            return;
        }

        $row = $this->localFornecedorResults[$index];
        $person = Person::query()->find($row['id']);

        if (! $person) {
            return;
        }

        $this->setLocalFornecedorSearchTerm(mb_strtoupper($person->nome_razao, 'UTF-8'));
        $this->fornecedorFilter = (string) $person->id;
        $this->onLocalFornecedorConfirmed($person);
        $this->localFornecedorLookupOpen = false;
        $this->localFornecedorResults = [];
        $this->selectedLocalFornecedorIndex = null;
        $this->clearListSelection();
        $this->resetTable();
    }

    public function handleLocalFornecedorEnter(): void
    {
        if (! $this->isLocalFornecedorSearchActive()) {
            return;
        }

        if (trim($this->localFornecedorSearchTerm()) === '') {
            $this->fornecedorFilter = 'todos';
            $this->closeLocalFornecedorLookup();
            $this->clearListSelection();
            $this->resetTable();

            return;
        }

        if ($this->localFornecedorLookupOpen) {
            if ($this->localFornecedorResults === []) {
                $this->localFornecedorLookupOpen = false;
                $this->clearListSelection();
                $this->resetTable();

                return;
            }

            $this->confirmLocalFornecedorSelection();
        }
    }

    public function closeLocalFornecedorLookup(): void
    {
        $this->localFornecedorLookupOpen = false;
        $this->localFornecedorResults = [];
        $this->selectedLocalFornecedorIndex = null;
    }

    protected function shouldSkipFornecedorSearchWhileTyping(): bool
    {
        return $this->isLocalFornecedorSearchActive()
            && $this->localFornecedorLookupOpen
            && filled(trim($this->localFornecedorSearchTerm()))
            && $this->fornecedorFilter === 'todos';
    }

    protected function onLocalFornecedorSearchTyped(string $value): void
    {
        if (! $this->isLocalFornecedorSearchActive()) {
            $this->closeLocalFornecedorLookup();

            return;
        }

        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->localFornecedorSearchTerm() !== $upper) {
            $this->setLocalFornecedorSearchTerm($upper);
        }

        $this->fornecedorFilter = 'todos';
        $this->localFornecedorLookupOpen = true;
        $this->refreshLocalFornecedorResults();
    }

    protected function localFornecedorSearchTerm(): string
    {
        if (property_exists($this, 'localSearchByField') && is_array($this->localSearchByField)) {
            return (string) ($this->localSearchByField['fornecedor'] ?? '');
        }

        return (string) ($this->localSearch ?? '');
    }

    protected function setLocalFornecedorSearchTerm(string $value): void
    {
        if (property_exists($this, 'localSearchByField') && is_array($this->localSearchByField)) {
            $this->localSearchByField['fornecedor'] = $value;

            return;
        }

        $this->localSearch = $value;
    }

    protected function onLocalFornecedorConfirmed(Person $person): void
    {
    }
}
