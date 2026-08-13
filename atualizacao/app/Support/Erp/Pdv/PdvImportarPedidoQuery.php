<?php

namespace App\Support\Erp\Pdv;

use App\Models\Venda;
use Illuminate\Database\Eloquent\Builder;

class PdvImportarPedidoQuery
{
    public function __construct(
        public ?string $numero = null,
        public ?string $dataDe = null,
        public ?string $dataAte = null,
    ) {}

    public function build(): Builder
    {
        $query = Venda::query()
            ->with(['cliente:id,nome_razao', 'vendedor:id,nome'])
            ->where('tipo', Venda::TIPO_PEDIDO)
            ->semDocumentoFiscalEmitido()
            ->whereHas('itens')
            ->orderByDesc('data')
            ->orderByDesc('id');

        $numero = trim((string) $this->numero);

        if ($numero !== '') {
            $like = '%' . $numero . '%';
            $query->where(function (Builder $q) use ($like, $numero): void {
                $q->where('numero', 'like', $like);

                if (ctype_digit($numero)) {
                    $q->orWhere('numero', ltrim($numero, '0') ?: '0');
                }
            });
        }

        if (filled($this->dataDe)) {
            $query->whereDate('data', '>=', $this->dataDe);
        }

        if (filled($this->dataAte)) {
            $query->whereDate('data', '<=', $this->dataAte);
        }

        return $query;
    }
}
