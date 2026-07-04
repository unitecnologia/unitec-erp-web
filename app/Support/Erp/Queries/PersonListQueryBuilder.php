<?php

namespace App\Support\Erp\Queries;

use App\Models\Person;
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

        return new self(
            statusFilter: in_array($status, $allowedStatus, true) ? $status : 'ativos',
            tipoFilter: in_array($tipo, $allowedTipo, true) ? $tipo : 'clientes',
            searchColumn: in_array($campo, $allowedCampo, true) ? $campo : 'nome_razao',
            localSearch: trim((string) $request->query('q', '')),
            orderBy: in_array($ordenar, $allowedOrder, true) ? $ordenar : 'codigo',
        );
    }

    public function build(): Builder
    {
        $query = Person::query();

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

        $allowedOrder = ['codigo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj'];
        $orderBy = in_array($this->orderBy, $allowedOrder, true) ? $this->orderBy : 'codigo';

        return $query->orderBy($orderBy);
    }

    protected function applySearch(Builder $query): void
    {
        $column = $this->searchColumn;
        $searchTerm = $this->localSearch;

        if (in_array($column, ['nome_razao', 'apelido_fantasia', 'endereco'], true)) {
            $searchTerm = mb_strtoupper($searchTerm, 'UTF-8');
        }

        if ($column === 'endereco') {
            $query->where(function (Builder $builder) use ($searchTerm): void {
                $term = '%' . $searchTerm . '%';
                $builder
                    ->where('endereco', 'like', $term)
                    ->orWhere('bairro', 'like', $term)
                    ->orWhere('cidade_nome', 'like', $term);
            });

            return;
        }

        $query->where($column, 'like', '%' . $searchTerm . '%');
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
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'ordenar' => $this->orderBy !== 'codigo' ? $this->orderBy : null,
        ];
    }
}
