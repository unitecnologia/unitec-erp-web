<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\CaixaLivroMovimento;
use App\Models\User;
use App\Support\Erp\ErpContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Linhas visuais de abertura/fechamento (não são lançamentos financeiros).
 */
final class CaixaLivroAberturaDisplay
{
    public const ID_BASE_ABERTURA = -1_000_000_000;

    public const ID_BASE_FECHAMENTO = -2_000_000_000;

    /** @deprecated Use ID_BASE_ABERTURA */
    public const ID_BASE = self::ID_BASE_ABERTURA;

    public static function isLinhaVirtual(int|string|null $id): bool
    {
        $id = (int) $id;

        return $id <= self::ID_BASE_ABERTURA;
    }

    public static function isLinhaAbertura(int|string|null $id): bool
    {
        $id = (int) $id;

        return $id <= self::ID_BASE_ABERTURA && $id > self::ID_BASE_FECHAMENTO;
    }

    public static function isLinhaFechamento(int|string|null $id): bool
    {
        $id = (int) $id;

        return $id <= self::ID_BASE_FECHAMENTO;
    }

    public static function movimentoIdFromVirtual(int $id): int
    {
        if (self::isLinhaFechamento($id)) {
            return abs($id - self::ID_BASE_FECHAMENTO);
        }

        return abs($id - self::ID_BASE_ABERTURA);
    }

    public static function movimentoAbertoNaConta(?int $contaId = null, ?int $empresaId = null): ?CaixaLivroMovimento
    {
        $svc = app(CaixaLivroMovimentoService::class);
        if (! $svc->tabelaPronta()) {
            return null;
        }

        $contaId = (int) ($contaId ?? 0);
        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($contaId <= 0 || $empresaId <= 0) {
            return null;
        }

        return $svc->movimentoAberto($empresaId, $contaId);
    }

    /**
     * Movimento vigente para status/rodapé: aberto atual, senão o último fechado.
     */
    public static function movimentoReferenciaNaConta(?int $contaId = null, ?int $empresaId = null): ?CaixaLivroMovimento
    {
        $svc = app(CaixaLivroMovimentoService::class);
        if (! $svc->tabelaPronta()) {
            return null;
        }

        $contaId = (int) ($contaId ?? 0);
        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($contaId <= 0 || $empresaId <= 0) {
            return null;
        }

        $aberto = $svc->movimentoAberto($empresaId, $contaId);
        if ($aberto) {
            return $aberto;
        }

        return CaixaLivroMovimento::query()
            ->with(['abertoPor:id,name', 'fechadoPor:id,name'])
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId)
            ->where('status', CaixaLivroMovimento::STATUS_FECHADO)
            ->orderByDesc('fechado_em')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Banner de status da conta filtrada.
     *
     * @return array{
     *     visivel: bool,
     *     aberto: bool,
     *     titulo: string,
     *     detalhe: string,
     *     movimento_id: int
     * }
     */
    public static function statusBanner(?int $contaId = null, ?int $empresaId = null): array
    {
        $mov = self::movimentoReferenciaNaConta($contaId, $empresaId);
        if (! $mov) {
            return [
                'visivel' => false,
                'aberto' => false,
                'titulo' => '',
                'detalhe' => '',
                'movimento_id' => 0,
            ];
        }

        if ($mov->isAberto()) {
            $quando = optional($mov->aberto_em)?->format('d/m/Y H:i') ?? '—';
            $quem = mb_strtoupper((string) ($mov->abertoPor?->name
                ?: User::query()->whereKey((int) $mov->aberto_por)->value('name')
                ?: '—'), 'UTF-8');
            $troco = number_format((float) $mov->troco_inicial, 2, ',', '.');

            return [
                'visivel' => true,
                'aberto' => true,
                'titulo' => 'CAIXA ABERTO',
                'detalhe' => 'Abertura '.$quando.' · '.$quem.' · Troco R$ '.$troco,
                'movimento_id' => (int) $mov->id,
            ];
        }

        $quando = optional($mov->fechado_em)?->format('d/m/Y H:i') ?? '—';
        $quem = mb_strtoupper((string) ($mov->fechadoPor?->name
            ?: User::query()->whereKey((int) $mov->fechado_por)->value('name')
            ?: '—'), 'UTF-8');

        return [
            'visivel' => true,
            'aberto' => false,
            'titulo' => 'CAIXA FECHADO',
            'detalhe' => 'Fechamento '.$quando.' · '.$quem,
            'movimento_id' => (int) $mov->id,
        ];
    }

    /**
     * @return CaixaLancamento|null registro sintético só para a grade
     */
    public static function linhaVirtual(?CaixaLivroMovimento $mov): ?CaixaLancamento
    {
        return self::linhaVirtualAbertura($mov);
    }

    public static function linhaVirtualAbertura(?CaixaLivroMovimento $mov): ?CaixaLancamento
    {
        if (! $mov) {
            return null;
        }

        $troco = round((float) $mov->troco_inicial, 2);
        $abertoEm = $mov->aberto_em instanceof Carbon
            ? $mov->aberto_em
            : Carbon::parse((string) $mov->aberto_em);

        $usuario = $mov->relationLoaded('abertoPor')
            ? (string) ($mov->abertoPor?->name ?? '')
            : (string) (User::query()->whereKey((int) $mov->aberto_por)->value('name') ?? '');
        $usuario = mb_strtoupper(trim($usuario), 'UTF-8');
        $hora = $abertoEm->format('H:i');
        $trocoFmt = number_format($troco, 2, ',', '.');

        $historico = 'ABERTURA DE CAIXA — TROCO INICIAL R$ '.$trocoFmt;
        if ($usuario !== '') {
            $historico .= ' · '.$usuario;
        }
        $historico .= ' · '.$hora;

        $linha = new CaixaLancamento;
        $linha->forceFill([
            'id' => self::ID_BASE_ABERTURA - (int) $mov->id,
            'codigo' => 0,
            'emissao' => $abertoEm->toDateString(),
            'documento' => 'ABERTURA',
            'historico' => $historico,
            'plano_contas' => 'CONFERÊNCIA',
            'caixa_conta_id' => (int) $mov->caixa_conta_id,
            'entrada' => $troco,
            'saida' => 0,
            'empresa_id' => (int) $mov->empresa_id,
        ]);
        $linha->exists = true;
        $linha->setAttribute('livro_abertura', true);
        $linha->setAttribute('livro_evento', 'abertura');
        $linha->setAttribute('livro_movimento_id', (int) $mov->id);
        $linha->setAttribute('livro_abertura_em', $abertoEm->format('d/m/Y H:i'));
        $linha->setAttribute('livro_sort_at', $abertoEm->format('Y-m-d H:i:s'));

        $conta = CaixaConta::query()->find((int) $mov->caixa_conta_id);
        if ($conta) {
            $linha->setRelation('conta', $conta);
        }

        return $linha;
    }

    public static function linhaVirtualFechamento(?CaixaLivroMovimento $mov): ?CaixaLancamento
    {
        if (! $mov || ! $mov->isFechado() || blank($mov->fechado_em)) {
            return null;
        }

        $fechadoEm = $mov->fechado_em instanceof Carbon
            ? $mov->fechado_em
            : Carbon::parse((string) $mov->fechado_em);

        $usuario = $mov->relationLoaded('fechadoPor')
            ? (string) ($mov->fechadoPor?->name ?? '')
            : (string) (User::query()->whereKey((int) $mov->fechado_por)->value('name') ?? '');
        $usuario = mb_strtoupper(trim($usuario), 'UTF-8');

        $contado = number_format((float) ($mov->valor_contado ?? 0), 2, ',', '.');
        $dif = $mov->diferenca !== null
            ? number_format((float) $mov->diferenca, 2, ',', '.')
            : '—';

        $historico = 'FECHAMENTO DE CAIXA — '.$fechadoEm->format('d/m/Y H:i');
        if ($usuario !== '') {
            $historico .= ' · '.$usuario;
        }
        $historico .= ' · CONTADO R$ '.$contado.' · DIF. R$ '.$dif;

        $linha = new CaixaLancamento;
        $linha->forceFill([
            'id' => self::ID_BASE_FECHAMENTO - (int) $mov->id,
            'codigo' => 0,
            'emissao' => $fechadoEm->toDateString(),
            'documento' => 'FECHAMENTO',
            'historico' => $historico,
            'plano_contas' => 'CONFERÊNCIA',
            'caixa_conta_id' => (int) $mov->caixa_conta_id,
            'entrada' => 0,
            'saida' => 0,
            'empresa_id' => (int) $mov->empresa_id,
        ]);
        $linha->exists = true;
        $linha->setAttribute('livro_fechamento', true);
        $linha->setAttribute('livro_evento', 'fechamento');
        $linha->setAttribute('livro_movimento_id', (int) $mov->id);
        $linha->setAttribute('livro_fechamento_em', $fechadoEm->format('d/m/Y H:i'));
        $linha->setAttribute('livro_sort_at', $fechadoEm->format('Y-m-d H:i:s'));

        $conta = CaixaConta::query()->find((int) $mov->caixa_conta_id);
        if ($conta) {
            $linha->setRelation('conta', $conta);
        }

        return $linha;
    }

    /**
     * Linhas virtuais do período (abertura/fechamento) — sem inventar lançamentos.
     *
     * @return list<CaixaLancamento>
     */
    public static function linhasVirtuaisNoPeriodo(
        int $contaId,
        ?string $periodoDe,
        ?string $periodoAte,
        ?int $empresaId = null,
    ): array {
        $svc = app(CaixaLivroMovimentoService::class);
        if (! $svc->tabelaPronta() || $contaId <= 0) {
            return [];
        }

        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            return [];
        }

        $de = filled($periodoDe) ? Carbon::parse($periodoDe)->startOfDay() : null;
        $ate = filled($periodoAte) ? Carbon::parse($periodoAte)->endOfDay() : null;

        $q = CaixaLivroMovimento::query()
            ->with(['abertoPor:id,name', 'fechadoPor:id,name'])
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId)
            ->orderByDesc('id');

        // Amplia busca: movimentos que tocam o período (abertura ou fechamento).
        if ($de && $ate) {
            $q->where(function ($w) use ($de, $ate): void {
                $w->whereBetween('aberto_em', [$de, $ate])
                    ->orWhereBetween('fechado_em', [$de, $ate])
                    ->orWhere(function ($w2) use ($de, $ate): void {
                        $w2->where('status', CaixaLivroMovimento::STATUS_ABERTO)
                            ->where('aberto_em', '<=', $ate)
                            ->where(function ($w3) use ($de): void {
                                $w3->whereNull('fechado_em')->orWhere('fechado_em', '>=', $de);
                            });
                    });
            });
        }

        $linhas = [];
        foreach ($q->limit(50)->get() as $mov) {
            $abertoEm = Carbon::parse($mov->aberto_em);
            if (self::dataNoPeriodo($abertoEm, $de, $ate)) {
                $ab = self::linhaVirtualAbertura($mov);
                if ($ab) {
                    $linhas[] = $ab;
                }
            }

            if ($mov->isFechado() && filled($mov->fechado_em)) {
                $fechadoEm = Carbon::parse($mov->fechado_em);
                if (self::dataNoPeriodo($fechadoEm, $de, $ate)) {
                    $fc = self::linhaVirtualFechamento($mov);
                    if ($fc) {
                        $linhas[] = $fc;
                    }
                }
            }
        }

        return $linhas;
    }

    private static function dataNoPeriodo(Carbon $dt, ?Carbon $de, ?Carbon $ate): bool
    {
        if ($de && $dt->lt($de)) {
            return false;
        }
        if ($ate && $dt->gt($ate)) {
            return false;
        }

        return true;
    }

    /**
     * Métricas de rodapé — aberto atual ou último fechado (persistido).
     *
     * @return array<string, mixed>
     */
    public static function metricasRodape(
        int $contaId,
        float $entradasPeriodo,
        float $saidasPeriodo,
        float $saldoFinanceiroAtual,
        ?int $empresaId = null,
    ): array {
        $mov = self::movimentoReferenciaNaConta($contaId, $empresaId);
        if (! $mov) {
            return [
                'tem_movimento' => false,
                'status_aberto' => false,
                'troco_inicial' => 0.0,
                'entradas' => $entradasPeriodo,
                'saidas' => $saidasPeriodo,
                'saldo_movimento' => round($entradasPeriodo - $saidasPeriodo, 2),
                'saldo_financeiro' => $saldoFinanceiroAtual,
                'saldo_abertura_financeiro' => 0.0,
                'valor_contado' => null,
                'diferenca' => null,
                'diferenca_conferencia' => 0.0,
            ];
        }

        if ($mov->isAberto()) {
            $troco = round((float) $mov->troco_inicial, 2);
            $saldoAbertura = round((float) $mov->saldo_abertura, 2);
            $saldoMovimento = round($troco + $entradasPeriodo - $saidasPeriodo, 2);

            return [
                'tem_movimento' => true,
                'status_aberto' => true,
                'troco_inicial' => $troco,
                'entradas' => $entradasPeriodo,
                'saidas' => $saidasPeriodo,
                'saldo_movimento' => $saldoMovimento,
                'saldo_financeiro' => $saldoFinanceiroAtual,
                'saldo_abertura_financeiro' => $saldoAbertura,
                'valor_contado' => null,
                'diferenca' => null,
                'diferenca_conferencia' => round($saldoFinanceiroAtual - ($saldoAbertura + $entradasPeriodo - $saidasPeriodo), 2),
            ];
        }

        // Fechado: usa totais persistidos do movimento (não recalcula inventando).
        $troco = round((float) $mov->troco_inicial, 2);
        $entradas = round((float) ($mov->total_entradas ?? 0), 2);
        $saidas = round((float) ($mov->total_saidas ?? 0), 2);
        $saldoMov = round((float) ($mov->saldo_calculado ?? ($troco + $entradas - $saidas)), 2);
        $saldoAbertura = round((float) $mov->saldo_abertura, 2);

        return [
            'tem_movimento' => true,
            'status_aberto' => false,
            'troco_inicial' => $troco,
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo_movimento' => $saldoMov,
            'saldo_financeiro' => $saldoFinanceiroAtual,
            'saldo_abertura_financeiro' => $saldoAbertura,
            'valor_contado' => $mov->valor_contado !== null ? round((float) $mov->valor_contado, 2) : null,
            'diferenca' => $mov->diferenca !== null ? round((float) $mov->diferenca, 2) : null,
            'diferenca_conferencia' => round(
                $saldoFinanceiroAtual - ($saldoAbertura + $entradas - $saidas),
                2
            ),
        ];
    }

    /**
     * @param  Collection<int, CaixaLancamento>  $items
     * @param  list<CaixaLancamento>  $virtuais
     * @return Collection<int, CaixaLancamento>
     */
    public static function mesclarVirtuaisNaGrade(Collection $items, array $virtuais): Collection
    {
        $items = $items
            ->reject(fn ($row): bool => self::isLinhaVirtual($row->getKey()))
            ->values();

        foreach ($virtuais as $v) {
            $items->push($v);
        }

        return $items->sortByDesc(function ($row): string {
            $sort = (string) ($row->getAttribute('livro_sort_at') ?? '');
            if ($sort !== '') {
                return $sort;
            }
            $emissao = (string) ($row->emissao?->format('Y-m-d') ?? $row->emissao ?? '1970-01-01');

            return $emissao.' 12:00:00';
        })->values();
    }
}
