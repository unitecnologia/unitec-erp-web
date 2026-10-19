<?php

namespace App\Support\Erp\Reports\Tabular\Concerns;

use App\Support\Erp\ErpContext;
use App\Support\Erp\Pdv\PdvMesaService;
use App\Support\Erp\Reports\ReportEmpresaScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait QueriesMesaPdvVendas
{
    protected function assertEmpresaRestaurante(): void
    {
        abort_unless(
            ErpContext::currentEmpresa()?->tipo_atividade === 'restaurante_lanchonete',
            403,
            'Relatório disponível apenas para empresas do tipo Restaurante/Lanchonete.'
        );
    }

    protected function mesaNumeroDisponivel(): bool
    {
        return Schema::hasColumn('pdv_vendas', 'pdv_mesa_numero');
    }

    /**
     * Nome físico (com DB_PREFIX) para usar em DB::raw — o Query Builder não prefixa raw.
     */
    protected function mesaVendasSql(): string
    {
        return static::sqlTable('pdv_vendas');
    }

    /**
     * Vendas comerciais do PDV originadas de mesa, no período, nas empresas do filtro.
     *
     * Tabelas sem prefixo em DB::table/join (o Grammar aplica DB_PREFIX uma vez).
     */
    protected function mesaVendasQuery(Request $request, CarbonInterface $de, CarbonInterface $ate): Builder
    {
        $empresaIds = ReportEmpresaScope::resolveIds($request);
        $vendas = $this->mesaVendasSql();

        return DB::table('pdv_vendas')
            ->join('pdv_caixa_sessoes', 'pdv_caixa_sessoes.id', '=', 'pdv_vendas.pdv_caixa_sessao_id')
            ->whereIn('pdv_caixa_sessoes.empresa_id', $empresaIds)
            ->whereNotNull('pdv_vendas.pdv_mesa_numero')
            ->where('pdv_vendas.pdv_mesa_numero', '>', 0)
            ->where(function ($q): void {
                $q->whereNull('pdv_vendas.origem')
                    ->orWhereNotIn('pdv_vendas.origem', ['regularizacao_fiscal', 'os_nfce_pecas']);
            })
            ->whereBetween(DB::raw("DATE(COALESCE({$vendas}.fechado_em, {$vendas}.created_at))"), [
                $de->toDateString(),
                $ate->toDateString(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    protected function mapaRotulosMesas(Request $request): array
    {
        $empresaIds = ReportEmpresaScope::resolveIds($request);
        $mapa = [];

        foreach ($empresaIds as $empresaId) {
            foreach (PdvMesaService::mapaRotulos((int) $empresaId) as $numero => $rotulo) {
                $mapa[(int) $numero] = $rotulo;
            }
        }

        return $mapa;
    }

    protected function rotuloMesaDoMapa(array $mapa, int $numero): string
    {
        return $mapa[$numero] ?? PdvMesaService::rotuloPadrao($numero);
    }

    protected function formatDuracaoSegundos(int $segundos): string
    {
        $segundos = max(0, $segundos);
        $h = intdiv($segundos, 3600);
        $m = intdiv($segundos % 3600, 60);

        if ($h > 0) {
            return sprintf('%dh %02dmin', $h, $m);
        }

        return sprintf('%dmin', $m);
    }

    /** Vendas efetivas do PDV: exclui canceladas/estornadas (situacao = C). */
    protected function whereVendaNaoCancelada(Builder $query): Builder
    {
        return $query->where(function ($q): void {
            $q->whereNull('pdv_vendas.situacao')->orWhere('pdv_vendas.situacao', '!=', 'C');
        });
    }

    protected function vendaEstaCancelada(?string $situacao): bool
    {
        return (string) $situacao === 'C';
    }

    protected function rotuloSituacaoVenda(?string $situacao): string
    {
        return match ((string) ($situacao ?: 'F')) {
            'C' => 'Cancelada',
            'F' => 'Fechada',
            default => (string) $situacao,
        };
    }
}
