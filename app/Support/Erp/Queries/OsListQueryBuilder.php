<?php

namespace App\Support\Erp\Queries;

use App\Models\OrdemServico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OsListQueryBuilder
{
    public function __construct(
        public string $statusFilter = 'todos',
        public string $searchColumn = 'cliente',
        public string $localSearch = '',
        public string $periodoDeApplied = '',
        public string $periodoAteApplied = '',
        public bool $applyDefaultOrder = true,
    ) {}

    /**
     * Query leve para a grade — colunas visíveis + relacionamentos mínimos.
     */
    public function buildForList(): Builder
    {
        $table = (new OrdemServico)->getTable();

        $query = $this->buildFilteredQuery()->select([
            "{$table}.id",
            "{$table}.numero",
            "{$table}.codigo_legado",
            "{$table}.situacao",
            "{$table}.data_inicio",
            "{$table}.hora_inicio",
            "{$table}.data_termino",
            "{$table}.hora_termino",
            "{$table}.cliente_id",
            "{$table}.atendente_id",
            "{$table}.nome",
            "{$table}.descricao",
            "{$table}.placa",
            "{$table}.subtotal_pecas",
            "{$table}.subtotal_servicos",
            "{$table}.total_produtos",
            "{$table}.total_servicos",
            "{$table}.total_geral",
            "{$table}.faturamento_pagamentos",
            "{$table}.envio_whats_status",
        ]);

        // ofMany fiscais: sem lista de colunas — select restrito deixa ordem_servico_id ambíguo no JOIN.
        $query->with([
            'cliente:id,codigo,nome_razao,cpf_cnpj,endereco,bairro,cidade_nome,cidade_codigo,uf,cep,rg_ie,tipo_contribuinte',
            'atendente:id,nome',
            'nfseAutorizada',
            'nfseItemAutorizado.nfse',
            'nfeAutorizada',
            'nfePecasEmitida',
            'nfceAutorizada',
            'nfcePecasEmitida',
        ]);

        if (! $this->applyDefaultOrder) {
            return $query;
        }

        return $query->orderByDesc('data_inicio')->orderByDesc('id');
    }

    public function sumFilteredTotal(): float
    {
        return (float) $this->buildFilteredQuery()->sum('total_geral');
    }

    public function countFiltered(): int
    {
        return (int) $this->buildFilteredQuery()->count();
    }

    protected function buildFilteredQuery(): Builder
    {
        $query = OrdemServico::query()->daEmpresaAtiva();

        if ($this->statusFilter === OrdemServico::SITUACAO_ABERTA) {
            $query->whereIn('situacao', [
                OrdemServico::SITUACAO_ABERTA,
                OrdemServico::SITUACAO_ANDAMENTO,
            ]);
        } elseif ($this->statusFilter === OrdemServico::SITUACAO_FINALIZADA) {
            $query->whereIn('situacao', [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ]);
        } elseif ($this->statusFilter === OrdemServico::SITUACAO_CANCELADA) {
            $query->where('situacao', OrdemServico::SITUACAO_CANCELADA);
        }

        if ($de = $this->normalizePeriodDate($this->periodoDeApplied)) {
            // data_inicio é DATE: comparação direta preserva o mesmo intervalo do whereDate e usa índice.
            $query->where('data_inicio', '>=', $de);
        }

        if ($ate = $this->normalizePeriodDate($this->periodoAteApplied)) {
            $query->where('data_inicio', '<=', $ate);
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

        $column = in_array($this->searchColumn, ['numero', 'cliente', 'atendente', 'placa', 'equipamento'], true)
            ? $this->searchColumn
            : 'cliente';

        $like = '%'.$term.'%';

        match ($column) {
            'numero' => $query->where(function (Builder $q) use ($like, $term): void {
                $q->where('numero', 'like', $like);
                if (is_numeric($term)) {
                    $q->orWhere('codigo_legado', (int) $term);
                }
            }),
            'cliente' => $query->where(function (Builder $q) use ($like): void {
                $q->where('nome', 'like', $like)
                    ->orWhereHas('cliente', fn (Builder $c): Builder => $c->where('nome_razao', 'like', $like));
            }),
            'atendente' => $query->whereHas('atendente', fn (Builder $a): Builder => $a->where('nome', 'like', $like)),
            'placa' => $query->where(function (Builder $q) use ($like): void {
                $q->where('placa', 'like', $like)->orWhere('placa_veiculo', 'like', $like);
            }),
            'equipamento' => $query->where(function (Builder $q) use ($like): void {
                $q->where('descricao', 'like', $like)
                    ->orWhere('descricao2', 'like', $like)
                    ->orWhere('modelo', 'like', $like)
                    ->orWhere('marca', 'like', $like)
                    ->orWhere('numero_serie', 'like', $like);
            }),
        };
    }

    protected function normalizePeriodDate(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $this->isValidPeriodIsoDate($value) ? $value : null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) === 1) {
            $iso = sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);

            return $this->isValidPeriodIsoDate($iso) ? $iso : null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function isValidPeriodIsoDate(string $iso): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
