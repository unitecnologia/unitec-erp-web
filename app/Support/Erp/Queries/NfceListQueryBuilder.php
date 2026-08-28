<?php

namespace App\Support\Erp\Queries;

use App\Models\PdvVendaNfce;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class NfceListQueryBuilder
{
    public function __construct(
        public string $statusFilter = PdvVendaNfce::TAB_TRANSMITIDOS,
        public string $searchColumn = 'serie',
        public string $localSearch = '',
        public string $periodoDe = '',
        public string $periodoAte = '',
        public string $chaveFilter = '',
        public ?int $empresaId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            statusFilter: PdvVendaNfce::normalizeTabFilter((string) $request->query('status', PdvVendaNfce::TAB_TRANSMITIDOS)),
            searchColumn: (string) $request->query('campo', 'serie'),
            localSearch: trim((string) $request->query('q', '')),
            periodoDe: (string) $request->query('de', ''),
            periodoAte: (string) $request->query('ate', ''),
            chaveFilter: trim((string) $request->query('chave', '')),
            empresaId: is_numeric($request->query('empresa')) ? (int) $request->query('empresa') : null,
        );
    }

    public function build(): Builder
    {
        $query = PdvVendaNfce::query()
            ->with([
                'pdvVenda.sessao.terminal',
                'pdvVenda.user',
                'pdvVenda.vendedor',
                'pdvVenda.venda',
                'pdvVenda.itens.product',
                'pdvVenda.person',
            ]);

        if ($this->empresaId) {
            $empresaId = $this->empresaId;
            $query->where(function (Builder $outer) use ($empresaId): void {
                $outer->where('empresa_id', $empresaId)
                    ->orWhere(function (Builder $inner) use ($empresaId): void {
                        $inner->whereNull('empresa_id')
                            ->whereHas('pdvVenda.sessao', fn (Builder $sessao): Builder => $sessao
                                ->where('empresa_id', $empresaId));
                    });
            });
        }

        $query->whereIn('status', PdvVendaNfce::statusesForTab($this->statusFilter));

        if (filled($this->periodoDe)) {
            $query->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda
                ->whereDate('fechado_em', '>=', $this->periodoDe));
        }

        if (filled($this->periodoAte)) {
            $query->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda
                ->whereDate('fechado_em', '<=', $this->periodoAte));
        }

        if (filled($this->chaveFilter)) {
            $digits = preg_replace('/\D/', '', $this->chaveFilter) ?? '';
            $query->where('chave', 'like', '%'.$digits.'%');
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, mb_strtoupper(trim($this->localSearch), 'UTF-8'));
        }

        return $query
            ->orderBy(
                PdvVendaNfce::query()->getModel()->getTable().'.id',
                'asc',
            );
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, [
            'serie', 'numero', 'chave', 'protocolo', 'cpf', 'caixa', 'usuario', 'vendedor', 'pedido',
        ], true) ? $this->searchColumn : 'serie';

        $like = '%'.$term.'%';

        match ($column) {
            'serie' => $query->where('serie', 'like', $like),
            'numero' => $query->where('numero', 'like', $like),
            'chave' => $query->where('chave', 'like', $like),
            'protocolo' => $query->where('protocolo', 'like', $like),
            'cpf' => $query->where(function (Builder $outer) use ($like): void {
                $outer->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda->where('cpf_nota', 'like', $like))
                    ->orWhereHas('pdvVenda.person', fn (Builder $person): Builder => $person->where('cpf_cnpj', 'like', $like));
            }),
            'caixa' => $query->whereHas('pdvVenda.sessao.terminal', fn (Builder $terminal): Builder => $terminal->where('nome', 'like', $like)),
            'usuario' => $query->whereHas('pdvVenda.user', fn (Builder $user): Builder => $user->where('name', 'like', $like)),
            'vendedor' => $query->where(function (Builder $outer) use ($like): void {
                $outer->whereHas('pdvVenda.vendedor', fn (Builder $vendedor): Builder => $vendedor->where('nome', 'like', $like))
                    ->orWhereHas('pdvVenda', fn (Builder $venda): Builder => $venda->where('vendedor_nome', 'like', $like));
            }),
            'pedido' => $query->whereHas('pdvVenda.venda', fn (Builder $venda): Builder => $venda->where('numero', 'like', $like)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function reportFilters(): array
    {
        return [
            'status' => $this->statusFilter,
            'de' => $this->periodoDe,
            'ate' => $this->periodoAte,
            'chave' => $this->chaveFilter,
            'campo' => $this->searchColumn,
            'q' => $this->localSearch,
            'empresa' => $this->empresaId,
        ];
    }
}
