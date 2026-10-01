<?php

namespace App\Support\Erp;

use App\Models\CompraItem;
use App\Models\NfeItem;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\PdvVendaNfce;
use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductCardexService
{
    private ?string $periodoDe = null;

    private ?string $periodoAte = null;

    /**
     * @return array{
     *     compras: array<int, array<string, string>>,
     *     vendas: array<int, array<string, string>>,
     *     nfe: array<int, array<string, string>>,
     *     nfce: array<int, array<string, string>>,
     *     os: array<int, array<string, string>>,
     *     nfse: array<int, array<string, string>>,
     *     totais: array<string, string|null>,
     *     resumo: array<string, string>
     * }
     */
    public function forProduct(Product $product, bool $prestador = false, ?string $de = null, ?string $ate = null): array
    {
        [$this->periodoDe, $this->periodoAte] = $this->normalizarPeriodo($de, $ate);

        $compras = $this->comprasRows($product);
        $vendas = $this->vendasRows($product);
        $nfe = $this->nfeDocumentRows($product, '55');
        $nfce = $this->nfceRows($product, $this->nfeDocumentRows($product, '65'));
        $this->marcarNfceContabilizada($vendas, $nfce);

        $os = [];
        $nfse = [];

        if ($prestador) {
            $os = $this->osRows($product);
            $nfse = $this->nfseRows($product);
            $this->marcarNfseContabilizada($os, $nfse);
        }

        $totalCompras = $this->sumColumn($compras, 'total_raw');
        $totalVendas = $this->sumColumn($vendas, 'total_raw');
        $totalNfe = $this->sumColumn($nfe, 'total_raw');
        $totalNfce = $this->sumColumn($nfce, 'total_raw');
        $totalOs = $this->sumColumn($os, 'total_raw');
        $totalNfse = $this->sumColumn($nfse, 'total_raw');
        $nfceJaEmVendas = $this->sumColumn(
            array_values(array_filter(
                $nfce,
                static fn (array $row): bool => ! empty($row['contabilizada_em_vendas']),
            )),
            'total_raw',
        );
        $nfseJaEmOs = $this->sumColumn(
            array_values(array_filter(
                $nfse,
                static fn (array $row): bool => ! empty($row['contabilizada_em_os']),
            )),
            'total_raw',
        );

        $dados = [
            'compras' => $this->stripRawKeys($compras),
            'vendas' => $this->stripRawKeys($vendas),
            'nfe' => $this->stripRawKeys($nfe),
            'nfce' => $this->stripRawKeys($nfce),
            'os' => $this->stripRawKeys($os),
            'nfse' => $this->stripRawKeys($nfse),
            'totais' => [
                'compras' => $this->money($totalCompras),
                'vendas' => $this->money($totalVendas),
                'nfe' => $this->money($totalNfe),
                'nfce' => $this->money($totalNfce),
                'nfce_inclusa' => $this->notaNfceInclusa($totalNfce, $nfceJaEmVendas),
                'os' => $this->money($totalOs),
                'nfse' => $this->money($totalNfse),
                'nfse_inclusa' => $this->notaJaIncluida($totalNfse, $nfseJaEmOs, 'OS'),
                'total_vendas' => $this->money(
                    $totalVendas
                    + $totalNfe
                    + ($totalNfce - $nfceJaEmVendas)
                    + $totalOs
                    + ($totalNfse - $nfseJaEmOs)
                ),
            ],
            'resumo' => [
                'e_medio' => number_format((float) ($product->e_medio ?? 0), 3, ',', '.'),
                'ult_compra' => number_format((float) ($product->ult_compra ?? 0), 2, ',', '.'),
                'ult_compra_anterior' => number_format((float) ($product->ult_compra_anterior ?? 0), 2, ',', '.'),
            ],
        ];

        $this->periodoDe = null;
        $this->periodoAte = null;

        return $dados;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function comprasRows(Product $product): array
    {
        $rows = [];

        foreach (
            CompraItem::query()
                ->with(['compra.fornecedor'])
                ->where('product_id', $product->id)
                ->tap(fn ($query) => $this->aplicarPeriodoCompra($query))
                ->orderByDesc('id')
                ->get() as $item
        ) {
            $compra = $item->compra;
            $dataEntrada = $compra?->data_entrada ?? $compra?->data_emissao;

            $rows[] = [
                'compra' => $compra?->numero ?? '—',
                'data_entrada' => $dataEntrada?->format('d/m/Y') ?? '—',
                'fornecedor' => $compra?->fornecedor?->nome_razao ?? '—',
                'quantidade' => $this->qty((float) $item->quantidade),
                'valor' => $this->money((float) $item->valor_unitario),
                'total' => $this->money((float) $item->total),
                'total_raw' => (float) $item->total,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function nfeDocumentRows(Product $product, string $modelo): array
    {
        $rows = [];
        $documentKey = $modelo === '65' ? 'nfce' : 'nfe';

        foreach (
            NfeItem::query()
                ->with(['nfe.cliente', 'nfe.venda'])
                ->where('product_id', $product->id)
                ->whereHas('nfe', function ($query) use ($modelo): void {
                    $query->where('modelo', $modelo);
                    $this->aplicarPeriodo($query, 'data_emissao');
                })
                ->orderByDesc('id')
                ->get() as $item
        ) {
            $nfe = $item->nfe;

            $row = [
                $documentKey => (string) ($nfe?->id ?? '—'),
                'numero' => $nfe?->numero ?? '—',
                'venda' => $this->numeroInterno($nfe?->venda?->numero ?? $nfe?->npedido),
                'data_emissao' => $nfe?->data_emissao?->format('d/m/Y') ?? '—',
                'hora_emissao' => $this->hora($nfe?->hora_emissao),
                'cliente' => $nfe?->cliente?->nome_razao ?? '—',
                'quantidade' => $this->qty((float) $item->quantidade),
                'valor' => $this->money((float) $item->valor_unitario),
                'desconto_acrescimo' => $this->ajusteLabel((float) $item->desconto, (float) $item->outros),
                'total' => $this->money((float) $item->total),
                'total_raw' => (float) $item->total,
            ];

            if ($modelo === '65') {
                $row['nfe_id'] = $nfe?->id !== null ? (int) $nfe->id : null;
                $row['venda_id'] = $nfe?->venda_id !== null ? (int) $nfe->venda_id : null;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    protected function hora(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $value = (string) $value;

        if (preg_match('/^\d{2}:\d{2}/', $value, $matches)) {
            return substr($matches[0], 0, 5);
        }

        return $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function vendasRows(Product $product): array
    {
        $itens = VendaItem::query()
            ->with(['venda.cliente'])
            ->where('product_id', $product->id)
            ->whereHas(
                'venda',
                fn ($query) => $query->where('status', '!=', Venda::STATUS_CANCELADO),
            )
            ->join('vendas', 'venda_itens.venda_id', '=', 'vendas.id')
            ->tap(fn ($query) => $this->aplicarPeriodo($query, 'vendas.data'))
            ->orderByDesc('vendas.data')
            ->orderByDesc('venda_itens.id')
            ->select('venda_itens.*')
            ->get();

        $ajustes = $this->ajustesIndexados(
            (int) $product->id,
            $itens->pluck('venda_id')->map(static fn ($id): int => (int) $id)->unique()->values()->all(),
        );

        $rows = [];

        foreach ($itens as $item) {
            $venda = $item->venda;
            $ajuste = $this->consumirAjuste($ajustes, $item);

            $rows[] = [
                'venda' => $this->numeroInterno($venda?->numero),
                'venda_id' => (int) $item->venda_id,
                'data_emissao' => $venda?->data?->format('d/m/Y') ?? '—',
                'cliente' => $venda?->cliente?->nome_razao ?? '—',
                'quantidade' => $this->qty((float) $item->quantidade),
                'valor' => $this->money((float) $item->valor_item),
                'desconto_acrescimo' => $this->ajusteLabel($ajuste['desconto'], $ajuste['acrescimo']),
                'total' => $this->money((float) $item->total),
                'total_raw' => (float) $item->total,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $vendaIds
     * @return array<string, list<object>>
     */
    protected function ajustesIndexados(int $productId, array $vendaIds): array
    {
        if ($vendaIds === []) {
            return [];
        }

        $pedido = DB::table('pedido_itens as i')
            ->join('forca_vendas_orders as o', 'o.pedido_id', '=', 'i.pedido_id')
            ->where('i.product_id', $productId)
            ->whereIn('o.venda_id', $vendaIds)
            ->select('o.venda_id as venda_id')
            ->addSelect('i.id as origem_id')
            ->selectRaw("'pedido' as origem")
            ->addSelect('i.quantidade as quantidade')
            ->addSelect('i.total as total')
            ->addSelect('i.desconto as desconto')
            ->selectRaw('0 as acrescimo');

        $pdv = DB::table('pdv_venda_itens as i')
            ->join('pdv_vendas as v', 'v.id', '=', 'i.pdv_venda_id')
            ->where('i.product_id', $productId)
            ->whereIn('v.venda_id', $vendaIds)
            ->whereNotNull('v.venda_id')
            ->select('v.venda_id as venda_id')
            ->addSelect('i.id as origem_id')
            ->selectRaw("'pdv' as origem")
            ->addSelect('i.quantidade as quantidade')
            ->addSelect('i.total as total')
            ->addSelect('i.desconto as desconto')
            ->addSelect('i.acrescimo as acrescimo');

        $index = [];

        foreach ($pedido->unionAll($pdv)->get() as $fonte) {
            $key = $this->chaveAjuste((int) $fonte->venda_id, (float) $fonte->quantidade, (float) $fonte->total);
            $index[$key][] = $fonte;
        }

        foreach ($index as &$fila) {
            usort($fila, static function (object $a, object $b): int {
                $porId = ((int) $a->origem_id) <=> ((int) $b->origem_id);

                return $porId !== 0 ? $porId : strcmp((string) $a->origem, (string) $b->origem);
            });
        }
        unset($fila);

        return $index;
    }

    /**
     * @param  array<string, list<object>>  $index
     * @return array{desconto: float, acrescimo: float}
     */
    protected function consumirAjuste(array &$index, VendaItem $item): array
    {
        $key = $this->chaveAjuste((int) $item->venda_id, (float) $item->quantidade, (float) $item->total);
        $fonte = null;

        if (! empty($index[$key])) {
            $fonte = array_shift($index[$key]);
        }

        if ($fonte === null) {
            return ['desconto' => 0.0, 'acrescimo' => 0.0];
        }

        if ((string) $fonte->origem === 'pdv') {
            $quantidade = (float) $fonte->quantidade;

            return [
                'desconto' => round((float) $fonte->desconto * $quantidade, 2),
                'acrescimo' => round((float) $fonte->acrescimo * $quantidade, 2),
            ];
        }

        return [
            'desconto' => round((float) $fonte->desconto, 2),
            'acrescimo' => 0.0,
        ];
    }

    protected function chaveAjuste(int $vendaId, float $quantidade, float $total): string
    {
        return $vendaId.'|'.number_format($quantidade, 3, '.', '').'|'.number_format($total, 2, '.', '');
    }

    protected function ajusteLabel(float $desconto, float $acrescimo): string
    {
        $partes = [];

        if ($desconto >= 0.005) {
            $partes[] = '- '.$this->money($desconto);
        }

        if ($acrescimo >= 0.005) {
            $partes[] = '+ '.$this->money($acrescimo);
        }

        return $partes === [] ? '—' : implode('  ', $partes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $modelo65
     * @return array<int, array<string, mixed>>
     */
    protected function nfceRows(Product $product, array $modelo65): array
    {
        $idsJaListados = [];

        foreach ($modelo65 as $row) {
            $id = (int) ($row['nfe_id'] ?? 0);

            if ($id > 0) {
                $idsJaListados[] = $id;
            }
        }

        $query = DB::table('pdv_venda_nfce as nf')
            ->join('pdv_vendas as v', 'v.id', '=', 'nf.pdv_venda_id')
            ->join('pdv_venda_itens as i', function ($join) use ($product): void {
                $join->on('i.pdv_venda_id', '=', 'v.id')
                    ->where('i.product_id', '=', $product->id);
            })
            ->leftJoin('vendas as vd', 'vd.id', '=', 'v.venda_id')
            ->leftJoin('people as pc', 'pc.id', '=', 'v.person_id')
            ->leftJoin('people as pvc', 'pvc.id', '=', 'vd.cliente_id')
            ->where('nf.status', PdvVendaNfce::STATUS_AUTORIZADA)
            ->tap(fn ($query) => $this->aplicarPeriodoDataHora($query, 'nf.autorizada_em'))
            ->orderByDesc('nf.id')
            ->orderByDesc('i.id')
            ->select([
                'nf.id as nfce_id',
                'nf.numero',
                'nf.nfe_id',
                'nf.autorizada_em',
                'v.venda_id',
                'vd.numero as venda_numero',
                'i.quantidade',
                'i.preco_unitario',
                'i.desconto',
                'i.acrescimo',
                'i.total',
                'pvc.nome_razao as cliente_venda',
                'pc.nome_razao as cliente_pdv',
            ]);

        if ($idsJaListados !== []) {
            $query->where(function ($filtro) use ($idsJaListados): void {
                $filtro->whereNull('nf.nfe_id')
                    ->orWhereNotIn('nf.nfe_id', $idsJaListados);
            });
        }

        $pdvRows = [];

        foreach ($query->get() as $item) {
            $momento = filled($item->autorizada_em) ? Carbon::parse((string) $item->autorizada_em) : null;

            $pdvRows[] = [
                'nfce' => (string) $item->nfce_id,
                'numero' => $item->numero !== null && $item->numero !== '' ? (string) $item->numero : '—',
                'venda' => $this->numeroInterno($item->venda_numero),
                'data_emissao' => $momento?->format('d/m/Y') ?? '—',
                'hora_emissao' => $momento?->format('H:i') ?? '—',
                'cliente' => filled($item->cliente_venda) ? (string) $item->cliente_venda : (filled($item->cliente_pdv) ? (string) $item->cliente_pdv : '—'),
                'quantidade' => $this->qty((float) $item->quantidade),
                'valor' => $this->money((float) $item->preco_unitario),
                'desconto_acrescimo' => $this->ajusteLabel(
                    round((float) $item->desconto * (float) $item->quantidade, 2),
                    round((float) $item->acrescimo * (float) $item->quantidade, 2),
                ),
                'total' => $this->money((float) $item->total),
                'total_raw' => (float) $item->total,
                'venda_id' => $item->venda_id !== null ? (int) $item->venda_id : null,
                'nfe_id' => $item->nfe_id !== null ? (int) $item->nfe_id : null,
            ];
        }

        return array_merge($modelo65, $pdvRows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function osRows(Product $product): array
    {
        $itens = DB::table('ordem_servico_itens as i')
            ->join('ordens_servico as o', 'o.id', '=', 'i.ordem_servico_id')
            ->leftJoin('people as c', 'c.id', '=', 'o.cliente_id')
            ->where('i.product_id', $product->id)
            ->where('o.situacao', '!=', OrdemServico::SITUACAO_CANCELADA)
            ->tap(fn ($query) => $this->aplicarPeriodo($query, 'o.data_inicio'))
            ->orderByDesc('o.data_inicio')
            ->orderByDesc('i.id')
            ->select('i.id as item_id')
            ->addSelect('o.id as os_id')
            ->addSelect('o.numero as numero')
            ->addSelect('o.data_inicio as data_inicio')
            ->addSelect('o.nome as nome_os')
            ->addSelect('c.nome_razao as cliente_cadastro')
            ->addSelect('i.qtd as qtd')
            ->addSelect('i.preco as preco')
            ->addSelect('i.desconto as desconto')
            ->addSelect('i.acrescimo as acrescimo')
            ->addSelect('i.total as total')
            ->get();

        $rows = [];

        foreach ($itens as $item) {
            $nome = trim((string) ($item->nome_os ?? ''));
            $cadastro = trim((string) ($item->cliente_cadastro ?? ''));

            $rows[] = [
                'os' => $this->numeroInterno($item->numero),
                'os_id' => (int) $item->os_id,
                'data' => $this->data($item->data_inicio),
                'cliente' => $nome !== '' ? $nome : ($cadastro !== '' ? $cadastro : '—'),
                'quantidade' => $this->qty((float) $item->qtd),
                'valor' => $this->money((float) $item->preco),
                'desconto_acrescimo' => $this->ajusteLabel((float) $item->desconto, (float) $item->acrescimo),
                'total' => $this->money((float) $item->total),
                'total_raw' => (float) $item->total,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function nfseRows(Product $product): array
    {
        $itens = DB::table('nfse_itens as i')
            ->join('nfses as n', 'n.id', '=', 'i.nfse_id')
            ->leftJoin('ordens_servico as o', 'o.id', '=', 'n.ordem_servico_id')
            ->where('i.product_id', $product->id)
            ->where('n.status', Nfse::STATUS_AUTORIZADA)
            ->tap(fn ($query) => $this->aplicarPeriodo($query, 'n.data_emissao'))
            ->orderByDesc('n.data_emissao')
            ->orderByDesc('i.id')
            ->select('i.id as item_id')
            ->addSelect('n.numero_nfse as numero_nfse')
            ->addSelect('n.numero_dps as numero_dps')
            ->addSelect('n.ordem_servico_id as ordem_servico_id')
            ->addSelect('o.numero as os_numero')
            ->addSelect('n.data_emissao as data_emissao')
            ->addSelect('n.tomador_nome as tomador_nome')
            ->addSelect('i.quantidade as quantidade')
            ->addSelect('i.valor as valor')
            ->addSelect('n.desconto as desconto')
            ->addSelect('i.total as total')
            ->get();

        $rows = [];

        foreach ($itens as $item) {
            $numero = trim((string) ($item->numero_nfse ?? ''));

            if ($numero === '' && $item->numero_dps !== null && (string) $item->numero_dps !== '') {
                $numero = (string) $item->numero_dps;
            }

            $rows[] = [
                'nfse' => $numero !== '' ? $numero : '—',
                'os' => $this->numeroInterno($item->os_numero),
                'ordem_servico_id' => (int) ($item->ordem_servico_id ?? 0),
                'data' => $this->data($item->data_emissao),
                'cliente' => trim((string) ($item->tomador_nome ?? '')) !== '' ? trim((string) $item->tomador_nome) : '—',
                'quantidade' => $this->qty((float) $item->quantidade),
                'valor' => $this->money((float) $item->valor),
                'desconto_acrescimo' => $this->ajusteLabel((float) $item->desconto, 0),
                'total' => $this->money((float) $item->total),
                'total_raw' => (float) $item->total,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $vendas
     * @param  array<int, array<string, mixed>>  $nfce
     */
    protected function marcarNfceContabilizada(array $vendas, array &$nfce): void
    {
        $ids = [];

        foreach ($vendas as $row) {
            $id = (int) ($row['venda_id'] ?? 0);

            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        foreach ($nfce as &$row) {
            $vendaId = (int) ($row['venda_id'] ?? 0);
            $row['contabilizada_em_vendas'] = $vendaId > 0 && isset($ids[$vendaId]);
        }
        unset($row);
    }

    /**
     * @param  array<int, array<string, mixed>>  $os
     * @param  array<int, array<string, mixed>>  $nfse
     */
    protected function marcarNfseContabilizada(array $os, array &$nfse): void
    {
        $ids = [];

        foreach ($os as $row) {
            $id = (int) ($row['os_id'] ?? 0);

            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        foreach ($nfse as &$row) {
            $osId = (int) ($row['ordem_servico_id'] ?? 0);
            $row['contabilizada_em_os'] = $osId > 0 && isset($ids[$osId]);
        }
        unset($row);
    }

    protected function notaNfceInclusa(float $totalNfce, float $jaEmVendas): ?string
    {
        return $this->notaJaIncluida($totalNfce, $jaEmVendas, 'Vendas');
    }

    protected function notaJaIncluida(float $total, float $jaIncluido, string $destino): ?string
    {
        if ($jaIncluido < 0.005) {
            return null;
        }

        if (abs($total - $jaIncluido) < 0.005) {
            return '(já incluída em '.$destino.')';
        }

        return '('.$this->money($jaIncluido).' já incluída em '.$destino.')';
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function normalizarPeriodo(?string $de, ?string $ate): array
    {
        $inicio = $this->dataIso($de);
        $fim = $this->dataIso($ate);

        if ($inicio === null || $fim === null) {
            return [null, null];
        }

        if ($inicio > $fim) {
            return [$fim, $inicio];
        }

        return [$inicio, $fim];
    }

    protected function dataIso(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        if (! $data instanceof \DateTimeImmutable || $data->format('Y-m-d') !== $valor) {
            return null;
        }

        return $valor;
    }

    protected function aplicarPeriodo(object $query, string $coluna): void
    {
        if ($this->periodoDe === null || $this->periodoAte === null) {
            return;
        }

        $query->whereBetween($coluna, [$this->periodoDe, $this->periodoAte]);
    }

    protected function aplicarPeriodoDataHora(object $query, string $coluna): void
    {
        if ($this->periodoDe === null || $this->periodoAte === null) {
            return;
        }

        $query->where($coluna, '>=', $this->periodoDe.' 00:00:00')
            ->where($coluna, '<=', $this->periodoAte.' 23:59:59');
    }

    protected function aplicarPeriodoCompra(object $query): void
    {
        if ($this->periodoDe === null || $this->periodoAte === null) {
            return;
        }

        $de = $this->periodoDe;
        $ate = $this->periodoAte;

        $query->whereHas('compra', function ($compra) use ($de, $ate): void {
            $compra->where(function ($filtro) use ($de, $ate): void {
                $filtro->whereBetween('data_entrada', [$de, $ate])
                    ->orWhere(function ($semEntrada) use ($de, $ate): void {
                        $semEntrada->whereNull('data_entrada')
                            ->whereBetween('data_emissao', [$de, $ate]);
                    });
            });
        });
    }

    protected function numeroInterno(mixed $numero): string
    {
        $numero = trim((string) $numero);

        if ($numero === '' || $numero === '—') {
            return '—';
        }

        if (preg_match('/^\d+$/', $numero) === 1) {
            $numero = ltrim($numero, '0');

            return $numero === '' ? '0' : $numero;
        }

        return $numero;
    }

    protected function data(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return Carbon::parse((string) $value)->format('d/m/Y');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, string>>
     */
    protected function stripRawKeys(array $rows): array
    {
        return array_map(static function (array $row): array {
            unset(
                $row['total_raw'],
                $row['venda_id'],
                $row['nfe_id'],
                $row['contabilizada_em_vendas'],
                $row['os_id'],
                $row['ordem_servico_id'],
                $row['contabilizada_em_os'],
            );

            return $row;
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function sumColumn(array $rows, string $key): float
    {
        return array_reduce(
            $rows,
            static fn (float $carry, array $row): float => $carry + (float) ($row[$key] ?? 0),
            0.0,
        );
    }

    protected function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    protected function qty(float $value): string
    {
        return number_format($value, 3, ',', '.');
    }
}
