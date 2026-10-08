<?php

namespace App\Support\Erp\Pdv;

use App\Models\PixCobranca;
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

        self::aplicarSomentePendentes($query);

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
     * Pedido pendente: somente ABERTO (gravado/fechado já é venda efetivada, com estoque
     * e financeiro; cancelado fica fora), sem faturamento e sem pagamento concluído:
     * - não originado do próprio PDV (espelho de venda);
     * - não gerado por faturamento do Força de Vendas (venda vinculada a um DAV);
     * - sem cobrança PIX paga.
     * Documento fiscal e venda PDV vinculada são filtrados em build()/applyFiltroTerminal().
     */
    public static function aplicarSomentePendentes(Builder $query): void
    {
        $query->where('status', Venda::STATUS_ABERTO);

        if (Schema::hasColumn('vendas', 'plataforma')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('plataforma')->orWhere('plataforma', '!=', Venda::PLATAFORMA_PDV);
            });
        }

        if (Schema::hasTable('forca_vendas_orders')) {
            $query->whereDoesntHave('forcaVendasOrder');
        }

        if (Schema::hasTable('pix_cobrancas') && Schema::hasColumn('pix_cobrancas', 'venda_id')) {
            $query->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('pix_cobrancas')
                    ->whereColumn('pix_cobrancas.venda_id', 'vendas.id')
                    ->where('pix_cobrancas.status', PixCobranca::STATUS_PAGO);
            });
        }
    }

    /**
     * API offline: pedidos do ERP (sem pdv_vendas) + deste terminal.
     * Exclui pedidos já vinculados a outro PDV/caixa.
     * Sem terminal (PDV online Filament): só pedidos sem venda PDV vinculada.
     */
    private function applyFiltroTerminal(Builder $query): void
    {
        $terminal = $this->terminal;

        if ($terminal === null) {
            $query->whereDoesntHave('pdvVenda');

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
