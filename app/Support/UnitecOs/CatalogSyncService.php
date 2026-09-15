<?php

namespace App\Support\UnitecOs;

use App\Models\Grupo;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\ProductEmpresaPrecoService;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo offline do app Unitec OS (clientes, produtos, grupos).
 */
final class CatalogSyncService
{
    /**
     * @return array{clientes: list<array<string, mixed>>, produtos: list<array<string, mixed>>, grupos: list<string>}
     */
    public function pull(User $user): array
    {
        $empresaId = (int) ($user->empresa_id ?? 0);
        if ($empresaId <= 0) {
            return ['clientes' => [], 'produtos' => [], 'grupos' => []];
        }

        return [
            'clientes' => $this->clientes(),
            'produtos' => $this->produtos($user, $empresaId),
            'grupos' => $this->grupos(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clientes(): array
    {
        return Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true)
            ->orderBy('nome_razao')
            ->get()
            ->map(static function (Person $person): array {
                $endereco = trim(implode(' — ', array_filter([
                    trim((string) ($person->endereco ?? '')),
                    trim((string) ($person->numero ?? '')) !== '' ? 'nº '.trim((string) $person->numero) : '',
                    trim((string) ($person->bairro ?? '')),
                    trim((string) ($person->cidade_nome ?? '')),
                    trim((string) ($person->uf ?? '')),
                ], static fn (string $p): bool => $p !== '')));

                return [
                    'id' => (int) $person->id,
                    'nome' => mb_strtoupper((string) $person->nome_razao, 'UTF-8'),
                    'fantasia' => mb_strtoupper((string) ($person->apelido_fantasia ?? ''), 'UTF-8'),
                    'telefone' => (string) ($person->fone1 ?: $person->celular1 ?: $person->fone2 ?: ''),
                    'email' => (string) ($person->email ?? ''),
                    'cpf_cnpj' => (string) ($person->cpf_cnpj ?? ''),
                    'cep' => (string) ($person->cep ?? ''),
                    'endereco' => mb_strtoupper(trim((string) ($person->endereco ?? '')), 'UTF-8'),
                    'endereco_completo' => mb_strtoupper($endereco, 'UTF-8'),
                    'numero' => (string) ($person->numero ?? ''),
                    'bairro' => mb_strtoupper((string) ($person->bairro ?? ''), 'UTF-8'),
                    'cidade' => mb_strtoupper((string) ($person->cidade_nome ?? ''), 'UTF-8'),
                    'uf' => mb_strtoupper((string) ($person->uf ?? ''), 'UTF-8'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function produtos(User $user, int $empresaId): array
    {
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

        $estoqueId = $this->estoqueDoUsuario($user);
        $reservados = (new EstoqueReservaService())->totaisReservadosAtivos($estoqueId);
        $precos = app(ProductEmpresaPrecoService::class);

        return Product::query()
            ->where('ativo', true)
            ->select($cols)
            ->orderBy('descricao')
            ->get()
            ->map(function (Product $p) use ($precos, $empresaId, $reservados): array {
                $preco = $precos->resolvePrecoVenda($p, $empresaId);
                $ean = trim((string) ($p->codigo_barras ?? ''));
                $eanCaixa = trim((string) ($p->codigo_barras_caixa ?? ''));
                // Usa saldo do cadastro (rápido no pull completo). Reservado vem do mapa em lote.
                $fisico = (float) ($p->estoque ?? 0);
                $reservado = (float) ($reservados[$p->id] ?? 0);
                $disponivel = $fisico - $reservado;

                return [
                    'id' => (int) $p->id,
                    'codigo' => (string) ($p->codigo ?? ''),
                    'codigo_barras' => $ean !== '' ? $ean : $eanCaixa,
                    'codigo_barras_caixa' => $eanCaixa !== '' && $eanCaixa !== $ean ? $eanCaixa : '',
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
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function grupos(): array
    {
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
            $nomes = Product::query()
                ->where('ativo', true)
                ->whereNotNull('grupo')
                ->where('grupo', '!=', '')
                ->distinct()
                ->orderBy('grupo')
                ->pluck('grupo')
                ->map(static fn ($n): string => trim((string) $n))
                ->filter(static fn (string $n): bool => $n !== '')
                ->values()
                ->all();
        }

        return $nomes;
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
}
