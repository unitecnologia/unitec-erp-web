<?php

namespace App\Filament\Concerns;

use App\Models\Person;

trait InteractsWithClienteFilterLookup
{
    public string $clienteSearch = '';

    public bool $clienteLookupOpen = false;

    /** @var array<int, array{id: int, nome: string, fantasia: string, cpf_cnpj: string}> */
    public array $clienteResults = [];

    public ?int $selectedClienteIndex = null;

    public function syncClienteFilterSearchFromSelection(): void
    {
        if ($this->clienteFilter !== 'todos' && is_numeric($this->clienteFilter)) {
            $person = Person::query()->find((int) $this->clienteFilter);
            $this->clienteSearch = mb_strtoupper($person?->nome_razao ?? '', 'UTF-8');

            return;
        }

        $this->clienteSearch = '';
    }

    public function updatedClienteSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->clienteSearch !== $upper) {
            $this->clienteSearch = $upper;
        }

        $this->clienteLookupOpen = true;
        $this->refreshClienteFilterResults();
    }

    public function openClienteFilterLookup(): void
    {
        $this->clienteLookupOpen = true;

        if (filled(trim($this->clienteSearch))) {
            $this->refreshClienteFilterResults();
        }
    }

    public function refreshClienteFilterResults(): void
    {
        $term = trim($this->clienteSearch);

        if ($term === '') {
            $this->clienteResults = [];
            $this->selectedClienteIndex = null;

            return;
        }

        $like = '%' . $term . '%';
        $digits = preg_replace('/\D/', '', $term) ?? '';

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true)
            ->where(function ($sub) use ($like, $digits, $term): void {
                $sub->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('cpf_cnpj', 'like', $like);

                if (strlen($digits) >= 2) {
                    $digitsLike = '%' . $digits . '%';
                    $sub->orWhereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                        [$digitsLike]
                    );
                }

                if (ctype_digit($term)) {
                    $sub->orWhere('codigo', 'like', $like);
                }
            });

        $this->clienteResults = $query
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

        $this->selectedClienteIndex = $this->clienteResults === [] ? null : 0;
    }

    public function moveClienteFilterSelection(int $delta): void
    {
        if ($this->clienteResults === []) {
            return;
        }

        $index = ($this->selectedClienteIndex ?? 0) + $delta;
        $count = count($this->clienteResults);
        $this->selectedClienteIndex = max(0, min($count - 1, $index));
    }

    public function selectClienteFilterResult(int $index): void
    {
        if (! isset($this->clienteResults[$index])) {
            return;
        }

        $this->selectedClienteIndex = $index;
        $this->confirmClienteFilterSelection();
    }

    public function confirmClienteFilterSelection(): void
    {
        $index = $this->selectedClienteIndex;

        if ($index === null || ! isset($this->clienteResults[$index])) {
            $this->clienteLookupOpen = false;

            return;
        }

        $row = $this->clienteResults[$index];
        $person = Person::query()->find($row['id']);

        if (! $person) {
            return;
        }

        $this->clienteSearch = mb_strtoupper($person->nome_razao, 'UTF-8');
        $this->clienteFilter = (string) $person->id;
        $this->clienteLookupOpen = false;
        $this->clienteResults = [];
        $this->selectedClienteIndex = null;
    }

    public function handleClienteFilterEnter(): void
    {
        if (trim($this->clienteSearch) === '') {
            $this->clearClienteFilterSelection();

            return;
        }

        if ($this->clienteLookupOpen) {
            if ($this->clienteResults === []) {
                $this->clienteLookupOpen = false;

                return;
            }

            $this->confirmClienteFilterSelection();
        }
    }

    public function closeClienteFilterLookup(): void
    {
        $this->clienteLookupOpen = false;
    }

    public function confirmClienteFilterSelectionOnBlur(): void
    {
        if (! $this->clienteLookupOpen) {
            return;
        }

        if ($this->selectedClienteIndex !== null && isset($this->clienteResults[$this->selectedClienteIndex])) {
            $this->confirmClienteFilterSelection();

            return;
        }

        $this->closeClienteFilterLookup();
    }

    public function clearClienteFilterSelection(): void
    {
        $this->clienteSearch = '';
        $this->clienteFilter = 'todos';
        $this->clienteLookupOpen = false;
        $this->clienteResults = [];
        $this->selectedClienteIndex = null;
    }
}
