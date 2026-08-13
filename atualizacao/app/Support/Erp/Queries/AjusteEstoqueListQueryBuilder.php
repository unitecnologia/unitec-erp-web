<?php

namespace App\Support\Erp\Queries;

use App\Models\AjusteEstoque;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AjusteEstoqueListQueryBuilder
{
    public function __construct(
        public bool $informarPeriodo = true,
        public string $periodoDe = '',
        public string $periodoAte = '',
        public string $searchColumn = 'produto',
        public string $localSearch = '',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $allowedCampo = ['produto', 'codigo', 'data'];

        $campo = (string) $request->query('campo', 'produto');

        return new self(
            informarPeriodo: $request->query('periodo', '1') !== '0',
            periodoDe: (string) $request->query('de', ''),
            periodoAte: (string) $request->query('ate', ''),
            searchColumn: in_array($campo, $allowedCampo, true) ? $campo : 'produto',
            localSearch: trim((string) $request->query('q', '')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function reportFilters(): array
    {
        return [
            'periodo' => $this->informarPeriodo ? '1' : '0',
            'de' => $this->periodoDe,
            'ate' => $this->periodoAte,
            'campo' => $this->searchColumn,
            'q' => $this->localSearch,
        ];
    }

    public function build(): Builder
    {
        $query = AjusteEstoque::query()->with(['product']);

        if ($this->informarPeriodo) {
            if (filled($this->periodoDe)) {
                $query->whereDate('data', '>=', $this->periodoDe);
            }

            if (filled($this->periodoAte)) {
                $query->whereDate('data', '<=', $this->periodoAte);
            }
        }

        if (filled($this->localSearch)) {
            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            $like = '%' . $term . '%';

            match ($this->searchColumn) {
                'codigo' => $query->whereHas(
                    'product',
                    fn (Builder $productQuery): Builder => $productQuery->where('codigo', 'like', $like),
                ),
                'data' => $query->whereDate(
                    'data',
                    str_contains($term, '/') ? implode('-', array_reverse(explode('/', $term))) : $term,
                ),
                default => $query->whereHas(
                    'product',
                    fn (Builder $productQuery): Builder => $productQuery->where('descricao', 'like', $like),
                ),
            };
        }

        return $query->orderByDesc('data')->orderByDesc('id');
    }
}
