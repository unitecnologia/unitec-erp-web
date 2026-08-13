<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Estoque;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\ProductEstoqueSaldoService;
use Illuminate\Support\Facades\Schema;

trait ManagesProductReservas
{
    /** @var array<int, array<string, mixed>> */
    public array $productReservasAtivas = [];

    public string $productEstoqueReservadoLabel = '0';

    public string $productEstoqueDisponivelLabel = '0';

    /**
     * @return list<array{key: string, nome: string, atual: string, reservado: string, disponivel: string, condicional: string, previsto: string}>
     */
    public function getProductEstoquePosicoesProperty(): array
    {
        $atualFallback = $this->parseBrDecimal($this->data['estoque'] ?? 0, 3);
        $reservado = $this->parseBrDecimal($this->productEstoqueReservadoLabel, 3);

        $empresaAtivaId = (int) (session('erp_empresa_id')
            ?? auth()->user()?->empresa_id
            ?? 0);

        $empresaIds = $this->productEstoqueEmpresaIds($empresaAtivaId);

        $estoques = collect();
        if ($empresaIds !== [] && Schema::hasTable('estoques')) {
            $estoques = Estoque::query()
                ->with(['empresa:id,codigo,nome'])
                ->whereIn('empresa_id', $empresaIds)
                ->where('ativo', true)
                ->orderBy('empresa_id')
                ->orderByRaw('CAST(codigo AS UNSIGNED)')
                ->orderBy('codigo')
                ->get(['id', 'empresa_id', 'codigo', 'nome']);
        }

        if ($estoques->isEmpty()) {
            $empresaNome = trim((string) (ErpContext::statusBar()['Empresa'] ?? ''));
            if ($empresaNome === '' || $empresaNome === '—') {
                $empresaNome = 'Loja ativa';
            }

            $disponivel = $atualFallback - $reservado;

            return [[
                'key' => 'loja-ativa',
                'nome' => '1 — ESTOQUE '.$empresaNome,
                'atual' => $this->formatBrDecimal($atualFallback, 3),
                'reservado' => $this->formatBrDecimal($reservado, 3),
                'disponivel' => $this->formatBrDecimal($disponivel, 3),
                'condicional' => $this->formatBrDecimal(0, 3),
                'previsto' => $this->formatBrDecimal(0, 3),
            ]];
        }

        $principalId = $estoques
            ->first(fn (Estoque $estoque): bool => (int) $estoque->empresa_id === $empresaAtivaId)
            ?->id
            ?? $estoques->first()?->id;

        $saldos = $this->productEstoqueSaldosByEstoqueId();
        $saldosService = app(ProductEstoqueSaldoService::class);
        $productId = (int) ($this->record?->id ?? $this->data['id'] ?? 0);

        return $estoques
            ->values()
            ->map(function (Estoque $estoque) use ($atualFallback, $reservado, $principalId, $saldosService, $productId): array {
                $isPrincipal = (int) $estoque->id === (int) $principalId;
                $atual = $productId > 0
                    ? $saldosService->fisico($productId, (int) $estoque->id)
                    : ($isPrincipal ? $atualFallback : 0.0);
                $reservadoPosicao = $isPrincipal ? $reservado : 0.0;
                $disponivel = $atual - $reservadoPosicao;
                $loja = trim((string) ($estoque->empresa?->nome ?? ''));
                $nome = $loja !== ''
                    ? $loja.' · '.$estoque->label()
                    : $estoque->label();

                return [
                    'key' => 'estoque-'.$estoque->id,
                    'nome' => $nome,
                    'atual' => $this->formatBrDecimal($atual, 3),
                    'reservado' => $this->formatBrDecimal($reservadoPosicao, 3),
                    'disponivel' => $this->formatBrDecimal($disponivel, 3),
                    'condicional' => $this->formatBrDecimal(0, 3),
                    'previsto' => $this->formatBrDecimal(0, 3),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, float>
     */
    protected function productEstoqueSaldosByEstoqueId(): array
    {
        if (! Schema::hasTable('product_estoque_saldos')) {
            return [];
        }

        $productId = (int) ($this->record?->id
            ?? $this->data['id']
            ?? 0);

        if ($productId <= 0) {
            return [];
        }

        return ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->pluck('quantidade', 'estoque_id')
            ->map(fn ($q): float => (float) $q)
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function productEstoqueEmpresaIds(int $empresaAtivaId): array
    {
        $user = auth()->user();

        if ($user instanceof User) {
            $ids = $user->accessibleEmpresaIds();
            if ($ids !== []) {
                return $ids;
            }
        }

        return $empresaAtivaId > 0 ? [$empresaAtivaId] : [];
    }

    /**
     * @return array{atual: string, reservado: string, disponivel: string, condicional: string, previsto: string}
     */
    public function getProductEstoqueTotaisProperty(): array
    {
        $atual = 0.0;
        $reservado = 0.0;
        $disponivel = 0.0;
        $condicional = 0.0;
        $previsto = 0.0;

        foreach ($this->productEstoquePosicoes as $row) {
            $atual += $this->parseBrDecimal($row['atual'] ?? 0, 3);
            $reservado += $this->parseBrDecimal($row['reservado'] ?? 0, 3);
            $disponivel += $this->parseBrDecimal($row['disponivel'] ?? 0, 3);
            $condicional += $this->parseBrDecimal($row['condicional'] ?? 0, 3);
            $previsto += $this->parseBrDecimal($row['previsto'] ?? 0, 3);
        }

        return [
            'atual' => $this->formatBrDecimal($atual, 3),
            'reservado' => $this->formatBrDecimal($reservado, 3),
            'disponivel' => $this->formatBrDecimal($disponivel, 3),
            'condicional' => $this->formatBrDecimal($condicional, 3),
            'previsto' => $this->formatBrDecimal($previsto, 3),
        ];
    }

    protected function loadProductReservas(?Product $product): void
    {
        if ($product === null || ! $product->exists) {
            $this->productReservasAtivas = [];
            $this->productEstoqueReservadoLabel = $this->formatBrDecimal(0, 3);
            $this->productEstoqueDisponivelLabel = $this->formatBrDecimal(
                $this->parseBrDecimal($this->data['estoque'] ?? 0, 3),
                3
            );

            return;
        }

        $serv = new EstoqueReservaService();
        $reservado = $serv->reservadoAtivo($product->id);
        $fisico = (float) $product->estoque;
        $disponivel = $fisico - $reservado;

        $this->productEstoqueReservadoLabel = $this->formatBrDecimal($reservado, 3);
        $this->productEstoqueDisponivelLabel = $this->formatBrDecimal($disponivel, 3);

        $this->productReservasAtivas = $serv->reservasAtivasDoProduto($product->id)
            ->map(fn ($r) => [
                'pedido' => $r->pedido_numero ?? ('FV-'.$r->forca_vendas_order_id),
                'cliente' => $r->cliente_nome ?? '—',
                'vendedor' => $r->vendedor_nome ?? '—',
                'plataforma' => strtoupper($r->plataforma),
                'quantidade' => $this->formatBrDecimal((float) $r->quantidade, 3),
                'data' => optional($r->created_at)->format('d/m/Y H:i') ?? '—',
            ])
            ->all();
    }
}
