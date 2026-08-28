<?php

namespace App\Support\Erp\Pdv;

use App\Models\Terminal;
use App\Models\Venda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class PdvImportarPedidoQuery
{
    public function __construct(
        public ?string $numero = null,
        public ?string $dataDe = null,
        public ?string $dataAte = null,
        public ?int $empresaId = null,
        public ?Terminal $terminal = null,
        public bool $somenteSemDocumentoFiscal = true,
    ) {}

    public function build(): Builder
    {
        $query = Venda::query()
            ->with(['cliente:id,nome_razao', 'vendedor:id,nome'])
            ->where('tipo', Venda::TIPO_PEDIDO)
            ->whereHas('itens')
            ->orderByDesc('data')
            ->orderByDesc('id');

        if ($this->somenteSemDocumentoFiscal) {
            $query->semDocumentoFiscalEmitido();
        }

        if (
            $this->empresaId !== null
            && $this->empresaId > 0
            && Schema::hasColumn('vendas', 'empresa_id')
        ) {
            $query->where('empresa_id', $this->empresaId);
        }

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

        $this->applyFiltroTerminal($query);

        return $query;
    }

    /**
     * API offline: pedidos do ERP (sem pdv_vendas) + deste terminal.
     * Exclui pedidos já vinculados a outro PDV/caixa.
     * Sem terminal (PDV online Filament): não aplica o filtro.
     */
    private function applyFiltroTerminal(Builder $query): void
    {
        $terminal = $this->terminal;

        if ($terminal === null) {
            return;
        }

        $nome = trim((string) ($terminal->nome ?? ''));
        $id = (int) ($terminal->id ?? 0);

        $query->where(function (Builder $q) use ($nome, $id): void {
            $q->whereDoesntHave('pdvVenda');

            if ($nome === '' && $id < 1) {
                return;
            }

            $q->orWhereHas('pdvVenda', function (Builder $pq) use ($nome, $id): void {
                $pq->where(function (Builder $t) use ($nome, $id): void {
                    if ($nome !== '') {
                        $t->where('terminal_offline', $nome);
                    }

                    if ($id > 0) {
                        $method = $nome !== '' ? 'orWhereHas' : 'whereHas';
                        $t->{$method}('sessao', fn (Builder $s) => $s->where('terminal_id', $id));
                    }
                });
            });
        });
    }
}
