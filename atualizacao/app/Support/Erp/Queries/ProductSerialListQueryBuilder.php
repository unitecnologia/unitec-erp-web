<?php

namespace App\Support\Erp\Queries;

use App\Models\Empresa;
use App\Models\ProductSerial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductSerialListQueryBuilder
{
    public function __construct(
        public string $searchColumn = 'descricao',
        public string $localSearch = '',
        public ?Empresa $empresa = null,
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

            if ($this->searchColumn === 'numero_serie') {
                $query->where('numero_serie', 'like', $parte . $term . '%');
            } else {
                $query->whereHas('product', fn (Builder $productQuery): Builder => $productQuery
                    ->where('descricao', 'like', $parte . $term . '%'));
            }
        }

        return $query->orderBy('numero_serie');
    }
}
