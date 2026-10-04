<?php

namespace App\Support\Erp\Queries;

use App\Models\Person;
use App\Support\Erp\ErpSearchFieldSelection;
use App\Support\Erp\ErpTableSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PersonListQueryBuilder
{
    public function __construct(
        public string $statusFilter = 'ativos',
        public string $tipoFilter = 'clientes',
        public string $searchColumn = 'nome_razao',
        public string $localSearch = '',
        public string $orderBy = 'codigo',
        public bool $applyDefaultOrder = true,
        public array $searchFieldsActive = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        $allowedStatus = ['ativos', 'inativos', 'todos'];
        $allowedTipo = [
            'clientes',
            'funcionarios',
            'fornecedores',
            'administradoras',
            'parceiros',
            'ccf_spc',
            'todos',
        ];
        $allowedCampo = ['codigo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj', 'rg_ie', 'endereco'];
        $allowedOrder = ['codigo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj'];

        $status = (string) $request->query('status', 'ativos');
        $tipo = (string) $request->query('tipo', 'clientes');
        $campo = (string) $request->query('campo', 'nome_razao');
        $ordenar = (string) $request->query('ordenar', 'codigo');
        $searchColumn = in_array($campo, $allowedCampo, true) ? $campo : 'nome_razao';
        $campos = $request->query('campos');
        $searchFieldsActive = is_string($campos) && $campos !== ''
            ? ErpSearchFieldSelection::normalize(array_map('trim', explode(',', $campos)), $allowedCampo, $searchColumn)
            : [];

        if ($searchFieldsActive !== []) {
            $searchColumn = $searchFieldsActive[array_key_last($searchFieldsActive)];
        }

        return new self(
            statusFilter: in_array($status, $allowedStatus, true) ? $status : 'ativos',
            tipoFilter: in_array($tipo, $allowedTipo, true) ? $tipo : 'clientes',
            searchColumn: $searchColumn,
            localSearch: trim((string) $request->query('q', '')),
            orderBy: in_array($ordenar, $allowedOrder, true) ? $ordenar : 'codigo',
            searchFieldsActive: $searchFieldsActive,
        );
    }

    /**
     * Query leve para a grade — colunas visíveis da listagem.
     */
    public function buildForList(): Builder
    {
        $peopleTable = (new Person)->getTable();

        $query = Person::query()->select([
            "{$peopleTable}.id",
            "{$peopleTable}.codigo",
            "{$peopleTable}.nome_razao",
            "{$peopleTable}.apelido_fantasia",
            "{$peopleTable}.cpf_cnpj",
            "{$peopleTable}.rg_ie",
            "{$peopleTable}.endereco",
            "{$peopleTable}.numero",
            "{$peopleTable}.bairro",
            "{$peopleTable}.cidade_nome",
            "{$peopleTable}.uf",
            "{$peopleTable}.fone1",
        ]);

        return $this->applyFilters($query);
    }

    /**
     * Query completa para relatórios/impressão.
     */
    public function build(): Builder
    {
        $query = Person::query();

        $this->applyFilters($query);

        return $this->applyDefaultOrder($query);
    }

    protected function applyFilters(Builder $query): Builder
    {
        match ($this->statusFilter) {
            'ativos' => $query->where('ativo', true),
            'inativos' => $query->where('ativo', false),
            default => $query,
        };

        match ($this->tipoFilter) {
            'clientes' => $query->where('is_cliente', true),
            'funcionarios' => $query->where('is_funcionario', true),
            'fornecedores' => $query->where('is_fornecedor', true),
            'administradoras' => $query->where('is_administradora', true),
            'parceiros' => $query->where('is_parceiro', true),
            'ccf_spc' => $query->where('is_ccf_spc', true),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applySearch($query);
        }

        return $query;
    }

    protected function applyDefaultOrder(Builder $query): Builder
    {
        if (! $this->applyDefaultOrder) {
            return $query;
        }

        $allowedOrder = ['codigo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj'];
        $orderBy = in_array($this->orderBy, $allowedOrder, true) ? $this->orderBy : 'codigo';

        if ($orderBy === 'codigo') {
            return ErpTableSort::orderByCodigoNumerico($query);
        }

        return $query->orderBy($orderBy);
    }

    /**
     * @return list<string>
     */
    protected function activeSearchColumns(): array
    {
        return ErpSearchFieldSelection::normalize(
            $this->searchFieldsActive,
            ['codigo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj', 'rg_ie', 'endereco'],
            $this->searchColumn,
        );
    }

    protected function applySearch(Builder $query): void
    {
        ErpSearchFieldSelection::applyOr(
            $query,
            $this->activeSearchColumns(),
            function (Builder $inner, string $column): void {
                $this->applySearchOnColumn($inner, $column);
            },
        );
    }

    protected function applySearchOnColumn(Builder $query, string $column): void
    {
        $searchTerm = $this->localSearch;

        if (in_array($column, ['nome_razao', 'apelido_fantasia', 'endereco'], true)) {
            $searchTerm = mb_strtoupper($searchTerm, 'UTF-8');
        }

        if ($column === 'nome_razao') {
            $terms = preg_split('/\s+/u', trim($searchTerm), -1, PREG_SPLIT_NO_EMPTY);

            if ($terms === false || $terms === []) {
                return;
            }

            foreach ($terms as $term) {
                $query->where('nome_razao', 'like', '%'.$term.'%');
            }

            return;
        }

        if ($column === 'endereco') {
            $query->where(function (Builder $builder) use ($searchTerm): void {
                $term = $searchTerm . '%';
                $builder
                    ->where('endereco', 'like', $term)
                    ->orWhere('bairro', 'like', $term)
                    ->orWhere('cidade_nome', 'like', $term);
            });

            return;
        }

        if ($column === 'cpf_cnpj') {
            $this->whereCpfCnpj($query, $searchTerm);

            return;
        }

        // codigo/rg/fantasia: prefixo indexável (digitação típica no campo).
        if (in_array($column, ['codigo', 'rg_ie', 'apelido_fantasia'], true)) {
            $query->where($column, 'like', $searchTerm . '%');

            return;
        }

        $query->where($column, 'like', $searchTerm . '%');
    }

    /**
     * Aceita o documento com ou sem máscara: 22469772 encontra 22.469.772/0001-00.
     */
    protected function whereCpfCnpj(Builder $query, string $searchTerm): void
    {
        $digits = preg_replace('/\D/', '', $searchTerm) ?? '';

        if ($digits === '') {
            $query->where('cpf_cnpj', 'like', $searchTerm.'%');

            return;
        }

        $query->whereRaw(
            "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
            [$digits.'%'],
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function reportFilters(): array
    {
        return [
            'tipo' => $this->tipoFilter !== 'clientes' ? $this->tipoFilter : null,
            'status' => $this->statusFilter !== 'ativos' ? $this->statusFilter : null,
            'campo' => $this->searchColumn !== 'nome_razao' ? $this->searchColumn : null,
            'campos' => count($this->activeSearchColumns()) > 1 ? implode(',', $this->activeSearchColumns()) : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'ordenar' => $this->orderBy !== 'codigo' ? $this->orderBy : null,
        ];
    }
}
