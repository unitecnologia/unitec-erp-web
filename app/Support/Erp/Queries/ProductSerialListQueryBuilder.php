<?php

namespace App\Support\Erp\Queries;

use App\Models\Empresa;
use App\Models\ProductSerial;
use App\Support\Erp\ErpSearchFieldSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductSerialListQueryBuilder
{
    public function __construct(
        public string $searchColumn = 'descricao',
        public string $localSearch = '',
        public ?Empresa $empresa = null,
        public array $searchFieldsActive = [],
    ) {}

    public static function fromRequest(Request $request, ?Empresa $empresa = null): self
    {
        $campo = $request->query('campo', 'descricao');
        $allowed = ['descricao', 'numero_serie'];

        return new self(
            searchColumn: in_array($campo, $allowed, true) ? (string) $campo : 'descricao',
            localSearch: trim((string) $request->query('q', '')),
            empresa: $empresa,
        );
    }

    public function build(): Builder
    {
        $query = ProductSerial::query()->with('product');

        if (filled($this->localSearch)) {
            $term = trim($this->localSearch);
            $parte = ($this->empresa?->param_pdv_pesquisa_partes_descricao ?? false) ? '%' : '';
            $columns = ErpSearchFieldSelection::normalize(
                $this->searchFieldsActive,
                ['descricao', 'numero_serie'],
                $this->searchColumn,
            );

            ErpSearchFieldSelection::applyOr($query, $columns, function (Builder $inner, string $column) use ($term, $parte): void {
                if ($column === 'numero_serie') {
                    $inner->where('numero_serie', 'like', $parte . $term . '%');

                    return;
                }

                $inner->whereHas('product', function (Builder $productQuery) use ($term): void {
                    ProductListQueryBuilder::whereDescricaoPartes($productQuery, 'descricao', $term);
                });
            });
        }

        return $query->orderBy('numero_serie');
    }
}
