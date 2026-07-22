<?php

namespace App\Support\ForcaVendas;

use App\Models\ContaReceber;
use App\Models\EstoqueReserva;
use App\Models\ForcaVendasClienteImport;
use App\Models\ForcaVendasOrder;
use App\Models\ForcaVendasVisitaSemVenda;
use App\Models\FormaPagamento;
use App\Models\Grupo;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Person;
use App\Models\PersonVisitaDia;
use App\Models\PriceTable;
use App\Models\Product;
use App\Models\ProductPriceTableItem;
use App\Models\Transportadora;
use App\Models\User;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\ProductEstoqueSaldoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ForcaVendasSyncService
{
    /**
     * Quantidade de dias de histÃ³rico de vendas trazidos no pull.
     */
    private const HISTORICO_DIAS = 120;

    /**
     * OrÃ§amentos do ERP enviados ao app (sobrevivem a reinstalaÃ§Ã£o).
     */
    private const ORCAMENTO_HISTORICO_DIAS = 30;

    /**
     * Monta o pacote de dados (pull) para o aplicativo.
     *
     * @return array<string, mixed>
     */
    public function buildPull(?Carbon $since, ?int $vendedorId = null): array
    {
        return [
            'server_time' => now()->toIso8601String(),
            'since' => $since?->toIso8601String(),
            'products' => $this->products($since, $vendedorId),
            'price_tables' => $this->priceTables($since),
            'price_table_items' => $this->priceTableItems($since),
            'customers' => $this->customers($since, $vendedorId),
            'visita_dias' => $this->visitaDias($vendedorId),
            'vendedores' => $this->vendedores(),
            'formas_pagamento' => $this->formasPagamento(),
            'transportadoras' => $this->transportadoras(),
            'grupos' => $this->grupos(),
            'financeiro' => $this->financeiro($since, $vendedorId),
            'historico_vendas' => $this->historicoVendas($since, $vendedorId),
            'historico_orcamentos' => $this->historicoOrcamentos($since, $vendedorId),
            'pedidos_fv' => $this->pedidosFv($since, $vendedorId),
        ];
    }

    /**
     * Assinatura barata para ETag: contagens + maior updated_at de cada tabela.
     */
    public function pullSignature(?int $vendedorId = null): string
    {
        // O histÃ³rico de vendas Ã© por vendedor, entÃ£o o ETag tambÃ©m precisa
        // variar por vendedor (senÃ£o um vendedor recebe o cache do outro).
        $parts = ['vendedor:'.($vendedorId ?? 0)];

        foreach ([
            'products' => Product::query(),
            'price_tables' => PriceTable::query(),
            'price_table_items' => ProductPriceTableItem::query(),
            'people' => $this->peopleSignatureQuery($vendedorId),
            'person_visita_dias' => $this->visitaDiasSignatureQuery($vendedorId),
            'vendedores' => Vendedor::query(),
            'formas_pagamento' => FormaPagamento::query()->where('disponivel_mobile', true),
            'transportadoras' => Schema::hasTable('transportadoras')
                ? Transportadora::query()->where('ativo', true)
                : null,
            'grupos' => Schema::hasTable('grupos')
                ? Grupo::query()
                    ->where('ativo', true)
                    ->when(
                        Schema::hasColumn('grupos', 'mostrar_no_app'),
                        fn ($query) => $query->where('mostrar_no_app', true),
                    )
                : null,
            'contas_receber' => $this->financeiroSignatureQuery($vendedorId),
            'vendas' => Venda::query(),
            'orcamentos' => Orcamento::query(),
            'forca_vendas_orders' => ForcaVendasOrder::query(),
        ] as $label => $query) {
            if ($query === null) {
                $parts[] = "{$label}:0:";
                continue;
            }
            $table = $query->getModel()->getTable();
            $count = (clone $query)->count();
            $max = Schema::hasColumn($table, 'updated_at')
                ? (string) (clone $query)->max('updated_at')
                : '';
            $parts[] = "{$label}:{$count}:{$max}";
        }

        $reservaCount = EstoqueReserva::query()->where('status', EstoqueReserva::STATUS_ATIVA)->count();
        $reservaMax = (string) EstoqueReserva::query()->max('updated_at');
        $parts[] = "estoque_reservas:{$reservaCount}:{$reservaMax}";

        if (Schema::hasTable('product_estoque_saldos')) {
            $saldoCount = (int) DB::table('product_estoque_saldos')->count();
            $saldoMax = (string) DB::table('product_estoque_saldos')->max('updated_at');
            $parts[] = "product_estoque_saldos:{$saldoCount}:{$saldoMax}";
        }

        $estoqueVendedor = $vendedorId
            ? (int) (Vendedor::query()->whereKey($vendedorId)->value('estoque_id') ?? 0)
            : 0;
        $parts[] = "vendedor_estoque:{$estoqueVendedor}";

        return sha1(implode('|', $parts));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(?Carbon $since, ?int $vendedorId = null): array
    {
        $estoqueId = null;
        if ($vendedorId) {
            $raw = Vendedor::query()->whereKey($vendedorId)->value('estoque_id');
            $estoqueId = $raw ? (int) $raw : null;
        }

        $saldos = new ProductEstoqueSaldoService();
        $reservados = (new EstoqueReservaService())->totaisReservadosAtivos($estoqueId);

        $query = Product::query();

        if ($since !== null && Schema::hasColumn('products', 'updated_at')) {
            $idsReservaAlterada = EstoqueReserva::query()
                ->where('updated_at', '>', $since)
                ->distinct()
                ->pluck('product_id')
                ->all();

            $idsComReservaAtiva = array_keys($reservados);
            $idsExtras = array_values(array_unique(array_merge($idsReservaAlterada, $idsComReservaAtiva)));

            $query->where(function ($q) use ($since, $idsExtras): void {
                $q->where('updated_at', '>', $since);

                if ($idsExtras !== []) {
                    $q->orWhereIn('id', $idsExtras);
                }
            });
        }

        return $query
            ->orderBy('id')
            ->get()
            ->map(function (Product $p) use ($reservados, $saldos, $estoqueId): array {
                $fisico = $saldos->fisico((int) $p->id, $estoqueId);
                $reservado = (float) ($reservados[$p->id] ?? 0);
                $disponivel = $fisico - $reservado;

                return [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'codigo_barras' => $p->codigo_barras,
                    'descricao' => $p->descricao,
                    'unidade' => $p->unidade,
                    'marca' => $p->marca,
                    'grupo' => $p->grupo,
                    'preco_venda' => (float) $p->preco_venda,
                    'preco_atacado' => (float) $p->preco_atacado,
                    'preco_especial' => (float) ($p->preco_especial ?? 0),
                    'qtd_atacado' => (float) $p->qtd_atacado,
                    'estoque' => $fisico,
                    'estoque_reservado' => $reservado,
                    'estoque_disponivel' => $disponivel,
                    'estoque_id' => $estoqueId,
                    'usa_tab_preco' => (bool) $p->usa_tab_preco,
                    'mostrar_no_app' => (bool) $p->mostrar_no_app,
                    'promo_preco_venda' => (float) $p->promo_preco_venda,
                    'promo_data_inicio' => optional($p->promo_data_inicio)->toDateString(),
                    'promo_data_fim' => optional($p->promo_data_fim)->toDateString(),
                    'foto_url' => $this->fotoApp($p),
                    'ativo' => (bool) $p->ativo,
                    'updated_at' => optional($p->updated_at)->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * URL pÃºblica (relativa) da foto do produto para o app. Retorna null quando
     * o produto nÃ£o tem foto. O app prefixa com o endereÃ§o do servidor.
     */
    private function fotoApp(Product $p): ?string
    {
        if (blank($p->foto_path)) {
            return null;
        }

        $url = route('forcavendas.produto.foto', ['product' => $p->id], false);
        $version = optional($p->updated_at)->timestamp;

        return $version ? $url . '?v=' . $version : $url;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function priceTables(?Carbon $since): array
    {
        return $this->applySince(PriceTable::query(), $since, 'price_tables')
            ->orderBy('id')
            ->get()
            ->map(fn (PriceTable $t): array => [
                'id' => $t->id,
                'codigo' => $t->codigo,
                'descricao' => $t->descricao,
                'ativo' => (bool) $t->ativo,
                'updated_at' => optional($t->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function priceTableItems(?Carbon $since): array
    {
        return $this->applySince(ProductPriceTableItem::query(), $since, 'product_price_table_items')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductPriceTableItem $i): array => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'price_table_id' => $i->price_table_id,
                'valor' => (float) $i->valor,
                'fator' => (float) $i->fator,
                'updated_at' => optional($i->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function customers(?Carbon $since, ?int $vendedorId = null): array
    {
        $query = Person::query()->where('is_cliente', true);

        if ($vendedorId !== null) {
            $query->where('vendedor_fv_id', $vendedorId);
        }

        return $this->applySince($query, $since, 'people')
            ->with('tabelaPrazo:id,dias')
            ->orderBy('id')
            ->get()
            ->map(fn (Person $c): array => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'nome_razao' => $c->nome_razao,
                'apelido_fantasia' => $c->apelido_fantasia,
                'cpf_cnpj' => $c->cpf_cnpj,
                'rg_ie' => $c->rg_ie,
                'endereco' => $c->endereco,
                'numero' => $c->numero,
                'bairro' => $c->bairro,
                'cidade_nome' => $c->cidade_nome,
                'uf' => $c->uf,
                'cep' => $c->cep,
                'email' => $c->email,
                'fone1' => $c->fone1,
                'celular1' => $c->celular1,
                'whatsapp' => $c->whatsapp,
                'limite_credito' => (float) $c->limite_credito,
                'dia_pgto' => $c->dia_pgto,
                'forma_pagamento_id' => $c->forma_pagamento_id,
                'tabela_prazo_id' => $c->tabela_prazo_id,
                'tabela_prazo_dias' => $c->tabelaPrazo?->dias,
                'price_table_id' => $c->price_table_id,
                'vendedor_fv_id' => $c->vendedor_fv_id,
                'vendedor_loja_id' => $c->vendedor_loja_id,
                'ativo' => (bool) $c->ativo,
                'updated_at' => optional($c->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Dias de visita (rotas) dos clientes da carteira do vendedor.
     *
     * @return array<int, array<string, mixed>>
     */
    private function visitaDias(?int $vendedorId = null): array
    {
        if (! Schema::hasTable('person_visita_dias')) {
            return [];
        }

        $query = PersonVisitaDia::query()
            ->whereHas('person', function ($q) use ($vendedorId): void {
                $q->where('is_cliente', true)
                    ->where('ativo', true);

                if ($vendedorId !== null) {
                    $q->where('vendedor_fv_id', $vendedorId);
                }
            })
            ->orderBy('dia_semana')
            ->orderBy('ordem');

        return $query
            ->get(['person_id', 'dia_semana', 'ordem'])
            ->map(fn (PersonVisitaDia $v): array => [
                'person_id' => (int) $v->person_id,
                'dia_semana' => (int) $v->dia_semana,
                'ordem' => (int) $v->ordem,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vendedores(): array
    {
        return Vendedor::query()
            ->with('tabelaVenda:id,codigo,descricao')
            ->orderBy('nome')
            ->get()
            ->map(fn (Vendedor $v): array => [
                'id' => $v->id,
                'codigo' => $v->codigo,
                'nome' => $v->nome,
                'ativo' => (bool) $v->ativo,
                'tabela_venda_id' => $v->tabela_venda_id,
                'tabela_venda_codigo' => $v->tabelaVenda?->codigo,
                'tabela_venda_descricao' => $v->tabelaVenda?->descricao,
            ])
            ->all();
    }

    /**
     * Formas de pagamento liberadas para o app (flag "DisponÃ­vel Mobile"),
     * jÃ¡ com as tabelas de prazo (parcelamentos) vinculadas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function formasPagamento(): array
    {
        return FormaPagamento::query()
            ->where('ativo', true)
            ->where('disponivel_mobile', true)
            ->with('tabelasPrazo')
            ->orderBy('codigo')
            ->get()
            ->map(fn (FormaPagamento $f): array => [
                'id' => $f->id,
                'codigo' => $f->codigo,
                'descricao' => $f->descricao,
                'tipo' => $f->tipo,
                'nfce' => (bool) $f->nfce,
                'max_parcelas' => $f->max_parcelas,
                'tabelas_prazo' => $f->tabelasPrazo
                    ->map(fn ($t): array => [
                        'id' => $t->id,
                        'dias' => $t->dias,
                        'ordem' => $t->ordem,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Grupos de produto ativos (fonte oficial do filtro no app).
     *
     * @return array<int, array<string, mixed>>
     */
    private function grupos(): array
    {
        if (! Schema::hasTable('grupos')) {
            return [];
        }

        return Grupo::query()
            ->where('ativo', true)
            ->when(
                Schema::hasColumn('grupos', 'mostrar_no_app'),
                fn ($query) => $query->where('mostrar_no_app', true),
            )
            ->orderBy('nome')
            ->get()
            ->map(fn (Grupo $g): array => [
                'id' => $g->id,
                'nome' => $g->nome,
                'ativo' => (bool) $g->ativo,
                'mostrar_no_app' => (bool) ($g->mostrar_no_app ?? false),
                'updated_at' => optional($g->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Transportadoras ativas para frete no app.
     *
     * @return array<int, array<string, mixed>>
     */
    private function transportadoras(): array
    {
        if (! Schema::hasTable('transportadoras')) {
            return [];
        }

        return Transportadora::query()
            ->where('ativo', true)
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->orderBy('proprietario')
            ->get()
            ->map(fn (Transportadora $t): array => [
                'id' => $t->id,
                'codigo' => $t->codigo,
                'nome' => filled($t->apelido) ? (string) $t->apelido : (string) $t->proprietario,
                'proprietario' => $t->proprietario,
                'apelido' => $t->apelido,
                'cnpj_cpf' => $t->cnpj_cpf,
                'cidade' => $t->cidade,
                'uf' => $t->uf,
                'ativo' => (bool) $t->ativo,
                'updated_at' => optional($t->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function financeiro(?Carbon $since, ?int $vendedorId = null): array
    {
        $query = ContaReceber::query()->where('saldo', '>', 0);

        if ($vendedorId !== null) {
            $query->whereIn('cliente_id', function ($sub) use ($vendedorId): void {
                $sub->select('id')
                    ->from('people')
                    ->where('is_cliente', true)
                    ->where('vendedor_fv_id', $vendedorId);
            });
        }

        return $this->applySince($query, $since, 'contas_receber')
            ->orderBy('vencimento')
            ->get()
            ->map(fn (ContaReceber $c): array => [
                'id' => $c->id,
                'numero' => $c->numero,
                'documento' => $c->documento,
                'cliente_id' => $c->cliente_id,
                'emissao' => optional($c->emissao)->toDateString(),
                'vencimento' => optional($c->vencimento)->toDateString(),
                'valor' => (float) $c->valor,
                'saldo' => (float) $c->saldo,
                'forma' => $c->forma,
                'updated_at' => optional($c->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historicoVendas(?Carbon $since, ?int $vendedorId = null): array
    {
        $query = Venda::query()
            ->with(['forcaVendasOrder.orcamento'])
            ->where('data', '>=', now()->subDays(self::HISTORICO_DIAS)->toDateString());

        // Cada vendedor enxerga apenas o prÃ³prio histÃ³rico de vendas.
        if ($vendedorId !== null) {
            $query->where('vendedor_id', $vendedorId);
        }

        return $this->applySince($query, $since, 'vendas')
            ->orderByDesc('data')
            ->limit(2000)
            ->get()
            ->map(fn (Venda $v): array => [
                'id' => $v->id,
                'numero' => $v->numero,
                'numero_orcamento' => $v->forcaVendasOrder?->orcamento?->numero,
                'data' => optional($v->data)->toDateString(),
                'cliente_id' => $v->cliente_id,
                'vendedor_id' => $v->vendedor_id,
                'total' => (float) $v->total,
                'status' => $v->status,
                'tipo' => $v->tipo,
                'updated_at' => optional($v->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * OrÃ§amentos do ERP (Ãºltimos 30 dias) do vendedor logado â€” exibidos no app
     * apÃ³s reinstalaÃ§Ã£o, junto com os orÃ§amentos locais da outbox.
     *
     * @return array<int, array<string, mixed>>
     */
    private function historicoOrcamentos(?Carbon $since, ?int $vendedorId = null): array
    {
        $query = Orcamento::query()
            ->with([
                'itens.product:id,descricao',
                'forcaVendasOrder:id,uuid,orcamento_id,tipo',
            ])
            ->where('data', '>=', now()->subDays(self::ORCAMENTO_HISTORICO_DIAS)->toDateString())
            // Pedidos do app já entram em pedidos_fv; aqui só orçamentos (ERP ou FV).
            ->where(function ($q): void {
                $q->whereDoesntHave('forcaVendasOrder')
                    ->orWhereHas(
                        'forcaVendasOrder',
                        fn ($fv) => $fv->where('tipo', ForcaVendasOrder::TIPO_ORCAMENTO),
                    );
            });

        if ($vendedorId !== null) {
            $query->where('vendedor_id', $vendedorId);
        }

        return $this->applySince($query, $since, 'orcamentos')
            ->orderByDesc('data')
            ->limit(1000)
            ->get()
            ->map(function (Orcamento $o): array {
                $fv = $o->forcaVendasOrder;
                $itens = $o->itens
                    ->map(fn ($item): array => [
                        'product_id' => $item->product_id,
                        'descricao' => $item->descricao ?: $item->product?->descricao,
                        'quantidade' => (float) $item->quantidade,
                        'preco_unitario' => (float) $item->preco_unitario,
                        'desconto' => (float) ($item->desconto ?? 0),
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => $o->id,
                    'uuid' => $fv?->uuid ?: ('erp-orc-'.$o->id),
                    'numero' => $o->numero,
                    'data' => optional($o->data)->toDateString(),
                    'cliente_id' => $o->cliente_id,
                    'vendedor_id' => $o->vendedor_id,
                    'total' => (float) $o->total,
                    'status' => $o->status,
                    'tipo' => 'orcamento',
                    'situacao' => $o->status,
                    'observacoes' => $o->observacoes,
                    'desconto_valor' => (float) ($o->desconto_valor ?? 0),
                    'percentual_desconto' => (float) ($o->percentual_desconto ?? 0),
                    'forma_pagamento' => $o->forma_pagamento,
                    'itens' => $itens,
                    'updated_at' => optional($o->updated_at)->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * Pedidos/orÃ§amentos enviados pelo app â€” atualiza nÃºmeros e situaÃ§Ã£o no aparelho.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pedidosFv(?Carbon $since, ?int $vendedorId = null): array
    {
        $query = ForcaVendasOrder::query()
            ->with([
                'orcamento.itens.product:id,descricao',
                'venda:id,numero',
            ])
            ->where('received_at', '>=', now()->subDays(self::HISTORICO_DIAS));

        if ($vendedorId !== null) {
            $query->where('vendedor_id', $vendedorId);
        }

        return $this->applySince($query, $since, 'forca_vendas_orders')
            ->orderByDesc('received_at')
            ->limit(2000)
            ->get()
            ->map(function (ForcaVendasOrder $order): array {
                $payload = is_array($order->payload) ? $order->payload : [];
                $orcamento = $order->orcamento;

                $itens = $orcamento?->itens
                    ->map(fn ($item): array => [
                        'product_id' => $item->product_id,
                        'descricao' => $item->descricao ?: $item->product?->descricao,
                        'quantidade' => (float) $item->quantidade,
                        'preco_unitario' => (float) $item->preco_unitario,
                        'desconto' => (float) ($item->desconto ?? 0),
                    ])
                    ->values()
                    ->all() ?? [];

                return [
                    'uuid' => $order->uuid,
                    'numero' => $orcamento?->numero,
                    'numero_pedido' => $order->venda?->numero,
                    'situacao' => $order->situacao,
                    'status' => $order->status,
                    'tipo' => $order->tipo,
                    'total' => (float) $order->total,
                    'cliente_id' => $order->cliente_id,
                    'observacoes' => $orcamento?->observacoes,
                    'desconto_valor' => (float) ($orcamento?->desconto_valor ?? 0),
                    'forma_pagamento' => $orcamento?->forma_pagamento ?? ($payload['forma_pagamento'] ?? null),
                    'condicao_pagamento' => $payload['condicao_pagamento'] ?? null,
                    'itens' => $itens,
                    'data' => optional($order->dataAberturaAt())->toDateString(),
                    'created_at' => optional($order->client_created_at ?? $order->received_at)->toIso8601String(),
                    'updated_at' => optional($order->updated_at)->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPushResult(ForcaVendasOrder $order, bool $duplicado = false): array
    {
        $order->loadMissing(['orcamento:id,numero', 'venda:id,numero']);

        return [
            'uuid' => $order->uuid,
            'status' => $order->status,
            'orcamento_id' => $order->orcamento_id,
            'numero' => $order->orcamento?->numero,
            'numero_pedido' => $order->venda?->numero,
            'total' => (float) $order->total,
            'duplicado' => $duplicado,
        ];
    }

    /**
     * Aplica o filtro incremental (delta) quando a coluna updated_at existir.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function applySince($query, ?Carbon $since, string $table)
    {
        if ($since !== null && Schema::hasColumn($table, 'updated_at')) {
            $query->where('updated_at', '>', $since);
        }

        return $query;
    }

    /**
     * Aplica os pedidos enviados pelo aplicativo (push), de forma idempotente por UUID.
     *
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<int, array<string, mixed>>
     */
    public function applyPush(array $orders, User $user): array
    {
        $results = [];

        foreach ($orders as $order) {
            $uuid = (string) ($order['uuid'] ?? '');

            if ($uuid === '') {
                $results[] = ['uuid' => null, 'status' => 'erro', 'erro' => 'UUID ausente.'];

                continue;
            }

            $existing = ForcaVendasOrder::query()->where('uuid', $uuid)->first();

            if ($existing !== null) {
                $results[] = $this->orderPushResult($existing, duplicado: true);

                continue;
            }

            $results[] = $this->createOrder($uuid, $order, $user);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function createOrder(string $uuid, array $order, User $user): array
    {
        try {
            return DB::transaction(function () use ($uuid, $order, $user): array {
                $clienteId = (int) ($order['cliente_id'] ?? 0);

                if ($clienteId <= 0 || ! Person::query()->whereKey($clienteId)->exists()) {
                    throw new \RuntimeException('Cliente invÃ¡lido ou nÃ£o encontrado.');
                }

                $itens = is_array($order['itens'] ?? null) ? $order['itens'] : [];

                if ($itens === []) {
                    throw new \RuntimeException('Pedido sem itens.');
                }

                $subtotal = 0.0;
                $descontoValor = (float) ($order['desconto_valor'] ?? 0);
                $clientCreatedAt = isset($order['created_at']) ? Carbon::parse($order['created_at']) : null;
                $momentoLocal = $clientCreatedAt
                    ? ErpTimezone::toLocal($clientCreatedAt)
                    : ErpTimezone::toLocal();
                $dataPedido = $momentoLocal->toDateString();

                $orcamento = Orcamento::query()->create([
                    'numero' => Orcamento::nextNumero(),
                    'data' => $dataPedido,
                    'hora' => $momentoLocal->format('H:i:s'),
                    'cliente_id' => $clienteId,
                    'vendedor_id' => $user->vendedor_id,
                    'subtotal' => 0,
                    'percentual_desconto' => (float) ($order['percentual_desconto'] ?? 0),
                    'desconto_valor' => $descontoValor,
                    'forma_pagamento' => $order['forma_pagamento'] ?? null,
                    'validade_dias' => (int) ($order['validade_dias'] ?? 0),
                    'observacoes' => $order['observacoes'] ?? null,
                    'total' => 0,
                    'status' => Orcamento::STATUS_ABERTO,
                    'plataforma' => Orcamento::PLATAFORMA_FV,
                ]);

                $linha = 1;

                foreach ($itens as $item) {
                    $productId = (int) ($item['product_id'] ?? 0);

                    if ($productId <= 0 || ! Product::query()->whereKey($productId)->exists()) {
                        throw new \RuntimeException('Produto invÃ¡lido no item '.$linha.'.');
                    }

                    $quantidade = (float) ($item['quantidade'] ?? 0);
                    $preco = (float) ($item['preco_unitario'] ?? 0);
                    $descItem = (float) ($item['desconto'] ?? 0);
                    $totalItem = round(($quantidade * $preco) - $descItem, 2);
                    $subtotal += $totalItem;

                    OrcamentoItem::query()->create([
                        'orcamento_id' => $orcamento->id,
                        'item' => $linha,
                        'product_id' => $productId,
                        'product_grade_id' => $item['product_grade_id'] ?? null,
                        'quantidade' => $quantidade,
                        'preco_unitario' => $preco,
                        'total' => $totalItem,
                        'desconto' => $descItem,
                        'descricao' => $item['descricao'] ?? null,
                    ]);

                    $linha++;
                }

                $total = round($subtotal - $descontoValor, 2);

                $orcamento->update([
                    'subtotal' => $subtotal,
                    'total' => $total,
                ]);

                // O pedido chega como "pendente" (situaÃ§Ã£o padrÃ£o). O faturamento
                // (venda + baixa de estoque + contas a receber) Ã© feito manualmente,
                // em lote, pela tela "Monitor de Vendas". Pedidos reservam estoque.
                $tipo = (string) ($order['tipo'] ?? ForcaVendasOrder::TIPO_ORCAMENTO);

                $fvOrder = ForcaVendasOrder::query()->create([
                    'uuid' => $uuid,
                    'device_uuid' => $order['device_uuid'] ?? null,
                    'user_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'tipo' => $tipo,
                    'cliente_id' => $clienteId,
                    'vendedor_id' => $user->vendedor_id,
                    'orcamento_id' => $orcamento->id,
                    'venda_id' => null,
                    'total' => $total,
                    'latitude' => $order['latitude'] ?? null,
                    'longitude' => $order['longitude'] ?? null,
                    'status' => ForcaVendasOrder::STATUS_IMPORTADO,
                    'payload' => $order,
                    'client_created_at' => $clientCreatedAt,
                    'received_at' => now(),
                ]);

                if ($tipo === ForcaVendasOrder::TIPO_PEDIDO) {
                    (new EstoqueReservaService())->reservarPedido($fvOrder, $orcamento, $user);
                }

                return array_merge(
                    $this->orderPushResult($fvOrder),
                    ['orcamento_id' => $orcamento->id],
                );
            });
        } catch (\Throwable $e) {
            ForcaVendasOrder::query()->updateOrCreate(
                ['uuid' => $uuid],
                [
                    'device_uuid' => $order['device_uuid'] ?? null,
                    'user_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'tipo' => $order['tipo'] ?? 'orcamento',
                    'cliente_id' => $order['cliente_id'] ?? null,
                    'vendedor_id' => $user->vendedor_id,
                    'total' => 0,
                    'latitude' => $order['latitude'] ?? null,
                    'longitude' => $order['longitude'] ?? null,
                    'status' => ForcaVendasOrder::STATUS_ERRO,
                    'erro' => $e->getMessage(),
                    'payload' => $order,
                    'received_at' => now(),
                ]
            );

            return [
                'uuid' => $uuid,
                'status' => ForcaVendasOrder::STATUS_ERRO,
                'erro' => $e->getMessage(),
            ];
        }
    }

    /**
     * Importa clientes cadastrados no app (idempotente por UUID).
     *
     * @param  array<int, array<string, mixed>>  $customers
     * @return array<int, array<string, mixed>>
     */
    public function applyCustomersPush(array $customers, User $user): array
    {
        $results = [];

        foreach ($customers as $customer) {
            $uuid = (string) ($customer['uuid'] ?? '');

            if ($uuid === '') {
                $results[] = ['uuid' => null, 'status' => 'erro', 'erro' => 'UUID ausente.'];

                continue;
            }

            $existing = ForcaVendasClienteImport::query()->where('uuid', $uuid)->first();

            if ($existing !== null) {
                $person = $existing->person;

                $results[] = [
                    'uuid' => $uuid,
                    'status' => 'importado',
                    'local_id' => $existing->local_id,
                    'person_id' => $existing->person_id,
                    'codigo' => $person?->codigo,
                    'duplicado' => true,
                ];

                continue;
            }

            try {
                $result = DB::transaction(function () use ($uuid, $customer, $user): array {
                    $nome = trim((string) ($customer['nome_razao'] ?? ''));

                    if ($nome === '') {
                        throw new \RuntimeException('Informe o nome ou razÃ£o social do cliente.');
                    }

                    $person = $this->findExistingCustomerByDocument($customer)
                        ?? $this->createCustomerFromApp($customer, $user);

                    $this->syncVisitaDiasFromApp($person, $customer);

                    if ($user->vendedor_id && $person->vendedor_fv_id === null) {
                        $person->update(['vendedor_fv_id' => $user->vendedor_id]);
                    }

                    $import = ForcaVendasClienteImport::query()->create([
                        'uuid' => $uuid,
                        'person_id' => $person->id,
                        'local_id' => isset($customer['local_id']) ? (int) $customer['local_id'] : null,
                        'device_uuid' => $customer['device_uuid'] ?? null,
                        'user_id' => $user->id,
                        'empresa_id' => $user->empresa_id,
                        'received_at' => now(),
                    ]);

                    return [
                        'uuid' => $uuid,
                        'status' => 'importado',
                        'local_id' => $import->local_id,
                        'person_id' => $person->id,
                        'codigo' => $person->codigo,
                    ];
                });

                $results[] = $result;
            } catch (\Throwable $e) {
                $results[] = [
                    'uuid' => $uuid,
                    'status' => 'erro',
                    'local_id' => $customer['local_id'] ?? null,
                    'erro' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function findExistingCustomerByDocument(array $customer): ?Person
    {
        $digits = preg_replace('/\D/', '', (string) ($customer['cpf_cnpj'] ?? ''));

        if ($digits === '') {
            return null;
        }

        return Person::query()
            ->where('is_cliente', true)
            ->whereRaw("REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') = ?", [$digits])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function createCustomerFromApp(array $customer, User $user): Person
    {
        $cpfCnpj = trim((string) ($customer['cpf_cnpj'] ?? ''));
        $uf = mb_strtoupper(trim((string) ($customer['uf'] ?? 'SC')), 'UTF-8');

        if ($uf === '') {
            $uf = 'SC';
        }

        return Person::query()->create([
            'codigo' => Person::nextCodigo(),
            'pessoa_tipo' => $this->inferPessoaTipo($cpfCnpj),
            'nome_razao' => mb_strtoupper(trim((string) ($customer['nome_razao'] ?? '')), 'UTF-8'),
            'apelido_fantasia' => mb_strtoupper(trim((string) ($customer['apelido_fantasia'] ?? '')) ?: '') ?: null,
            'cpf_cnpj' => $cpfCnpj !== '' ? $cpfCnpj : null,
            'rg_ie' => ($ie = trim((string) ($customer['rg_ie'] ?? ''))) !== '' ? mb_strtoupper($ie, 'UTF-8') : null,
            'endereco' => trim((string) ($customer['endereco'] ?? '')) ?: null,
            'numero' => trim((string) ($customer['numero'] ?? '')) ?: null,
            'bairro' => trim((string) ($customer['bairro'] ?? '')) ?: null,
            'cidade_nome' => trim((string) ($customer['cidade_nome'] ?? '')) ?: null,
            'uf' => $uf,
            'cep' => trim((string) ($customer['cep'] ?? '')) ?: null,
            'email' => trim((string) ($customer['email'] ?? '')) ?: null,
            'fone1' => trim((string) ($customer['fone1'] ?? '')) ?: null,
            'celular1' => trim((string) ($customer['celular1'] ?? '')) ?: null,
            'whatsapp' => trim((string) ($customer['whatsapp'] ?? '')) ?: null,
            'limite_credito' => (float) ($customer['limite_credito'] ?? 0),
            'dia_pgto' => isset($customer['dia_pgto']) && $customer['dia_pgto'] !== null && $customer['dia_pgto'] !== ''
                ? (int) $customer['dia_pgto']
                : null,
            'forma_pagamento_id' => isset($customer['forma_pagamento_id']) && $customer['forma_pagamento_id'] !== null && $customer['forma_pagamento_id'] !== ''
                ? (int) $customer['forma_pagamento_id']
                : null,
            'tabela_prazo_id' => isset($customer['tabela_prazo_id']) && $customer['tabela_prazo_id'] !== null && $customer['tabela_prazo_id'] !== ''
                ? (int) $customer['tabela_prazo_id']
                : null,
            'vendedor_fv_id' => $user->vendedor_id,
            'regime_tributario' => 'simples',
            'tipo_contribuinte' => 'nao_contribuinte',
            'is_cliente' => true,
            'ativo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function syncVisitaDiasFromApp(Person $person, array $customer): void
    {
        if (! Schema::hasTable('person_visita_dias') || ! array_key_exists('visita_dias', $customer)) {
            return;
        }

        $raw = $customer['visita_dias'];
        $dias = collect(is_array($raw) ? $raw : [])
            ->map(fn ($d) => (int) $d)
            ->filter(fn (int $d): bool => array_key_exists($d, PersonVisitaDia::diasLabels()))
            ->unique()
            ->values()
            ->all();

        $existentes = $person->visitaDias()->get()->keyBy('dia_semana');

        foreach ($dias as $dia) {
            if ($existentes->has($dia)) {
                continue;
            }

            $person->visitaDias()->create([
                'dia_semana' => $dia,
                'ordem' => PersonVisitaDia::nextOrdem(
                    $dia,
                    $person->vendedor_fv_id ? (int) $person->vendedor_fv_id : null
                ),
            ]);
        }

        foreach ($existentes as $dia => $visita) {
            if (! in_array((int) $dia, $dias, true)) {
                $visita->delete();
            }
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Person>
     */
    private function peopleSignatureQuery(?int $vendedorId)
    {
        $query = Person::query()->where('is_cliente', true);

        if ($vendedorId !== null) {
            $query->where('vendedor_fv_id', $vendedorId);
        }

        return $query;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\PersonVisitaDia>|null
     */
    private function visitaDiasSignatureQuery(?int $vendedorId)
    {
        if (! Schema::hasTable('person_visita_dias')) {
            return null;
        }

        return PersonVisitaDia::query()
            ->whereHas('person', function ($q) use ($vendedorId): void {
                $q->where('is_cliente', true);

                if ($vendedorId !== null) {
                    $q->where('vendedor_fv_id', $vendedorId);
                }
            });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ContaReceber>
     */
    private function financeiroSignatureQuery(?int $vendedorId)
    {
        $query = ContaReceber::query()->where('saldo', '>', 0);

        if ($vendedorId !== null) {
            $query->whereIn('cliente_id', function ($sub) use ($vendedorId): void {
                $sub->select('id')
                    ->from('people')
                    ->where('is_cliente', true)
                    ->where('vendedor_fv_id', $vendedorId);
            });
        }

        return $query;
    }

    private function inferPessoaTipo(?string $cpfCnpj): string
    {
        $digits = preg_replace('/\D/', '', (string) $cpfCnpj);

        return strlen($digits) > 11
            ? Person::PESSOA_JURIDICA
            : Person::PESSOA_FISICA;
    }

    /**
     * Registra visitas sem venda enviadas pelo app (idempotente por UUID).
     *
     * @param  array<int, array<string, mixed>>  $visitas
     * @return array<int, array<string, mixed>>
     */
    public function applyVisitasPush(array $visitas, User $user): array
    {
        $results = [];

        foreach ($visitas as $visita) {
            $uuid = (string) ($visita['uuid'] ?? '');

            if ($uuid === '') {
                $results[] = ['uuid' => null, 'status' => 'erro', 'erro' => 'UUID ausente.'];

                continue;
            }

            $existing = ForcaVendasVisitaSemVenda::query()->where('uuid', $uuid)->first();

            if ($existing !== null) {
                $results[] = [
                    'uuid' => $uuid,
                    'status' => $existing->status,
                    'duplicado' => true,
                ];

                continue;
            }

            try {
                $clienteId = (int) ($visita['cliente_id'] ?? 0);

                if ($clienteId <= 0 || ! Person::query()->whereKey($clienteId)->exists()) {
                    throw new \RuntimeException('Cliente invÃ¡lido ou nÃ£o encontrado.');
                }

                $motivo = trim((string) ($visita['motivo'] ?? ''));

                if (mb_strlen($motivo) < 10) {
                    throw new \RuntimeException('Informe o motivo com pelo menos 10 caracteres.');
                }

                $clientCreatedAt = isset($visita['created_at']) ? Carbon::parse($visita['created_at']) : null;

                ForcaVendasVisitaSemVenda::query()->create([
                    'uuid' => $uuid,
                    'device_uuid' => $visita['device_uuid'] ?? null,
                    'user_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'cliente_id' => $clienteId,
                    'vendedor_id' => $user->vendedor_id,
                    'motivo' => $motivo,
                    'latitude' => $visita['latitude'] ?? null,
                    'longitude' => $visita['longitude'] ?? null,
                    'status' => ForcaVendasVisitaSemVenda::STATUS_IMPORTADO,
                    'client_created_at' => $clientCreatedAt,
                    'received_at' => now(),
                ]);

                $results[] = [
                    'uuid' => $uuid,
                    'status' => ForcaVendasVisitaSemVenda::STATUS_IMPORTADO,
                ];
            } catch (\Throwable $e) {
                ForcaVendasVisitaSemVenda::query()->updateOrCreate(
                    ['uuid' => $uuid],
                    [
                        'device_uuid' => $visita['device_uuid'] ?? null,
                        'user_id' => $user->id,
                        'empresa_id' => $user->empresa_id,
                        'cliente_id' => $visita['cliente_id'] ?? null,
                        'vendedor_id' => $user->vendedor_id,
                        'motivo' => trim((string) ($visita['motivo'] ?? '')),
                        'latitude' => $visita['latitude'] ?? null,
                        'longitude' => $visita['longitude'] ?? null,
                        'status' => ForcaVendasVisitaSemVenda::STATUS_ERRO,
                        'erro' => $e->getMessage(),
                        'received_at' => now(),
                    ]
                );

                $results[] = [
                    'uuid' => $uuid,
                    'status' => ForcaVendasVisitaSemVenda::STATUS_ERRO,
                    'erro' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
