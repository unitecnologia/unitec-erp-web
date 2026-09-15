<?php

namespace App\Http\Controllers\Api\UnitecOs;

use App\Models\Grupo;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductSerial;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\Os\OsProdutoBusca;
use App\Support\Erp\ProductEmpresaPrecoService;
use App\Support\Erp\ProductEstoqueSaldoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProdutoController
{
    public function index(Request $request, ProductEmpresaPrecoService $precos): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = (int) ($user->empresa_id ?? 0);
        if ($empresaId <= 0) {
            return response()->json(['data' => []]);
        }

        $term = trim((string) $request->query('q', ''));
        $tipo = strtolower(trim((string) $request->query('tipo', 'produto')));
        $grupo = trim((string) $request->query('grupo', ''));

        $query = Product::query()->where('ativo', true);

        if (Schema::hasColumn('products', 'is_servico')) {
            if ($tipo === 'servico') {
                $query->where('is_servico', true);
            } elseif ($tipo !== 'todos') {
                $query->where(function ($q): void {
                    $q->where('is_servico', false)->orWhereNull('is_servico');
                });
            }
        }

        if ($grupo !== '' && Schema::hasColumn('products', 'grupo')) {
            $query->where('grupo', $grupo);
        }

        OsProdutoBusca::aplicar($query, $term);

        $cols = ['id', 'codigo', 'codigo_barras', 'codigo_barras_caixa', 'descricao', 'unidade', 'preco_venda', 'estoque'];
        if (Schema::hasColumn('products', 'is_servico')) {
            $cols[] = 'is_servico';
        }
        if (Schema::hasColumn('products', 'grupo')) {
            $cols[] = 'grupo';
        }
        if (Schema::hasColumn('products', 'foto_path')) {
            $cols[] = 'foto_path';
        }
        if (Schema::hasColumn('products', 'updated_at')) {
            $cols[] = 'updated_at';
        }

        $query->select($cols);
        $this->anexarIdentificadores($query, $term);

        $estoqueId = $this->estoqueDoUsuario($user);
        $saldos = new ProductEstoqueSaldoService();
        $reservados = (new EstoqueReservaService())->totaisReservadosAtivos($estoqueId);

        $itens = $query
            ->limit(100)
            ->get()
            ->map(function (Product $p) use ($precos, $empresaId, $saldos, $reservados, $estoqueId): array {
                $preco = $precos->resolvePrecoVenda($p, $empresaId);
                $ean = trim((string) ($p->codigo_barras ?? ''));
                $eanCaixa = trim((string) ($p->codigo_barras_caixa ?? ''));
                $fisico = $saldos->fisico((int) $p->id, $estoqueId);
                $reservado = (float) ($reservados[$p->id] ?? 0);
                $disponivel = $fisico - $reservado;

                return [
                    'id' => (int) $p->id,
                    'codigo' => (string) ($p->codigo ?? ''),
                    'codigo_barras' => $ean !== '' ? $ean : $eanCaixa,
                    'codigo_barras_caixa' => $eanCaixa !== '' && $eanCaixa !== $ean ? $eanCaixa : '',
                    'imei' => trim((string) ($p->getAttribute('imei_exibicao') ?? '')),
                    'numero_serie' => trim((string) ($p->getAttribute('serie_exibicao') ?? '')),
                    'descricao' => mb_strtoupper((string) ($p->descricao ?? ''), 'UTF-8'),
                    'unidade' => (string) ($p->unidade ?? 'UN'),
                    'grupo' => trim((string) ($p->grupo ?? '')),
                    'preco' => round((float) $preco, 2),
                    'estoque' => round($fisico, 3),
                    'estoque_reservado' => round($reservado, 3),
                    'estoque_disponivel' => round($disponivel, 3),
                    'foto_url' => $this->fotoApp($p),
                    'is_servico' => (bool) ($p->is_servico ?? false),
                ];
            })
            ->values();

        return response()->json(['data' => $itens]);
    }

    public function grupos(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = (int) ($user->empresa_id ?? 0);
        if ($empresaId <= 0) {
            return response()->json(['data' => []]);
        }

        $nomes = [];

        if (Schema::hasTable('grupos')) {
            $query = Grupo::query()->where('ativo', true);
            if (Schema::hasColumn('grupos', 'mostrar_no_app')) {
                $query->where('mostrar_no_app', true);
            }
            $nomes = $query
                ->orderBy('nome')
                ->pluck('nome')
                ->map(static fn ($n): string => trim((string) $n))
                ->filter(static fn (string $n): bool => $n !== '')
                ->values()
                ->all();
        }

        if ($nomes === [] && Schema::hasColumn('products', 'grupo')) {
            $query = Product::query()
                ->where('ativo', true)
                ->whereNotNull('grupo')
                ->where('grupo', '!=', '');

            if (Schema::hasColumn('products', 'is_servico')) {
                $query->where(function ($q): void {
                    $q->where('is_servico', false)->orWhereNull('is_servico');
                });
            }

            $nomes = $query
                ->distinct()
                ->orderBy('grupo')
                ->pluck('grupo')
                ->map(static fn ($n): string => trim((string) $n))
                ->filter(static fn (string $n): bool => $n !== '')
                ->values()
                ->all();
        }

        return response()->json(['data' => $nomes]);
    }

    private function estoqueDoUsuario(User $user): ?int
    {
        $vendedorId = $user->vendedor_id ? (int) $user->vendedor_id : 0;
        if ($vendedorId <= 0) {
            return null;
        }

        $raw = Vendedor::query()->whereKey($vendedorId)->value('estoque_id');

        return $raw ? (int) $raw : null;
    }

    private function fotoApp(Product $p): ?string
    {
        if (blank($p->foto_path)) {
            return null;
        }

        $url = route('unitecos.produto.foto', ['product' => $p->id], false);
        $version = optional($p->updated_at)->timestamp;

        return $version ? $url.'?v='.$version : $url;
    }

    private function anexarIdentificadores($query, string $term): void
    {
        $termo = trim($term);

        $query->addSelect([
            'imei_exibicao' => ProductImei::query()
                ->select('imei')
                ->whereColumn('product_id', $query->getModel()->getTable().'.id')
                ->when($termo !== '', function ($sub) use ($termo): void {
                    $sub->orderByRaw('CASE WHEN imei = ? THEN 0 ELSE 1 END', [$termo]);
                })
                ->orderBy('id')
                ->limit(1),
            'serie_exibicao' => ProductSerial::query()
                ->select('numero_serie')
                ->whereColumn('product_id', $query->getModel()->getTable().'.id')
                ->when($termo !== '', function ($sub) use ($termo): void {
                    $sub->orderByRaw('CASE WHEN numero_serie = ? THEN 0 ELSE 1 END', [$termo]);
                })
                ->orderBy('id')
                ->limit(1),
        ]);
    }
}
