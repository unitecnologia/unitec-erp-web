<?php

namespace App\Support\Erp\Pdv;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\ContaReceber;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Models\PlanoConta;
use App\Models\User;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Transfere o saldo da sessão PDV para o Livro Caixa no fechamento.
 *
 * Só entra o que já está na sessão (formas com tipo_movimento = caixa e recebimentos
 * de CR no PDV, que não passam pelo Livro Caixa na baixa). Cartão/boleto/crediário com
 * Contas a Receber nunca entram na sessão: o valor fica em títulos até a baixa.
 * Abertura/suprimento (fundo de troco) não são transferidos. Sangria é só saída do
 * Caixa PDV durante a sessão: não movimenta o Livro Caixa; o valor retirado chega ao
 * CAIXA GERAL aqui, dentro das vendas/recebimentos integrais.
 *
 * Um lançamento por origem × forma no CAIXA GERAL, preservando a classificação:
 * - vendas − estornos → plano de venda da empresa   (PDV-CX-{s}[-FORMA]);
 * - recebimentos de CR → plano dos títulos, se único (PDV-CX-{s}-RC-FORMA).
 * Idempotente por `documento`. Sessões fechadas no modelo anterior podem ter
 * PDV-CX-{s}-SG{mov} (sangria na subcaixa) e PDV-CX-{s}-SD-FORMA: são preservados.
 */
final class PdvCaixaFechamentoService
{
    private const TIPOS_FORA_DA_TRANSFERENCIA = ['abertura', 'suprimento', 'sangria'];

    private const ORIGEM_VENDA = 'venda';

    private const ORIGEM_RECEBIMENTO = 'recebimento';

    private const ORIGEM_OUTROS = 'outros';

    /**
     * @return list<CaixaLancamento>
     */
    public function lancarNoLivroCaixa(PdvCaixaSessao $sessao, ?User $usuario = null): array
    {
        if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
            return [];
        }

        $usuario ??= Auth::user() ?? $sessao->user;
        $nome = mb_strtoupper(trim((string) ($usuario?->name ?? 'USUARIO')), 'UTF-8');
        $fechamento = $sessao->fechado_em
            ? ErpTimezone::toLocal($sessao->fechado_em)
            : ErpTimezone::toLocal();
        $empresaId = $sessao->empresa_id ? (int) $sessao->empresa_id : ErpContext::currentEmpresaId();

        $movimentos = $sessao->movimentos()->orderBy('id')->get();
        $caixaGeral = CaixaConta::ensureCaixaGeral();
        $lancamentos = [];

        $planos = [
            self::ORIGEM_VENDA => EmpresaParametros::planoVendaLancamento($empresaId),
            self::ORIGEM_RECEBIMENTO => $this->planoDosTitulosRecebidos($movimentos, $empresaId),
            self::ORIGEM_OUTROS => null,
        ];

        foreach ($this->saldosPorOrigemForma($movimentos) as $origem => $porForma) {
            foreach ($porForma as $forma => $saldo) {
                if (abs($saldo) < 0.005) {
                    continue;
                }

                $lancamento = $this->lancar(
                    documento: $this->documento($sessao, $origem, $forma),
                    emissao: $fechamento->toDateString(),
                    historico: $this->historicoFechamento($nome, $fechamento, $origem, $forma),
                    contaId: (int) $caixaGeral->id,
                    empresaId: $empresaId,
                    valor: $saldo,
                    plano: $planos[$origem] ?? null,
                );

                if ($lancamento) {
                    $lancamentos[] = $lancamento;
                }
            }
        }

        return $lancamentos;
    }

    /**
     * Gera lançamentos de sessões fechadas que nunca foram transferidas ao Livro Caixa.
     * Sessões já lançadas (mesmo que parcialmente, no modelo antigo) não são tocadas.
     */
    public function backfillSessoesRecentes(int $dias = 14): int
    {
        if (! Schema::hasTable((new PdvCaixaSessao)->getTable())) {
            return 0;
        }

        $desde = Carbon::now()->subDays($dias);
        $criados = 0;

        $sessoes = PdvCaixaSessao::query()
            ->with('user')
            ->whereNotNull('fechado_em')
            ->where('fechado_em', '>=', $desde)
            ->orderByDesc('id')
            ->get();

        foreach ($sessoes as $sessao) {
            $base = 'PDV-CX-'.$sessao->id;

            $jaLancada = CaixaLancamento::query()
                ->where(fn ($query) => $query
                    ->where('documento', $base)
                    ->orWhere('documento', 'like', $base.'-%'))
                ->exists();

            if ($jaLancada) {
                continue;
            }

            if ($this->lancarNoLivroCaixa($sessao, $sessao->user) !== []) {
                $criados++;
            }
        }

        return $criados;
    }

    /**
     * Resultado líquido da sessão por origem e forma.
     *
     * Abertura e suprimento ficam fora: são fundo de troco que não gera saída em nenhuma
     * conta do Livro Caixa; transferi-los inflaria o CAIXA GERAL a cada fechamento.
     * Sangria também fica fora: é só retirada da gaveta do Caixa PDV; o dinheiro retirado
     * faz parte das vendas/recebimentos que vão integrais ao CAIXA GERAL aqui.
     *
     * @param  iterable<PdvCaixaMovimento>  $movimentos
     * @return array<string, array<string, float>>
     */
    private function saldosPorOrigemForma(iterable $movimentos): array
    {
        $saldos = [];

        foreach ($movimentos as $movimento) {
            $tipo = (string) $movimento->tipo;

            if (in_array($tipo, self::TIPOS_FORA_DA_TRANSFERENCIA, true)) {
                continue;
            }

            $origem = match ($tipo) {
                'venda', 'estorno' => self::ORIGEM_VENDA,
                'recebimento' => self::ORIGEM_RECEBIMENTO,
                default => self::ORIGEM_OUTROS,
            };

            $forma = mb_strtoupper(trim((string) ($movimento->forma_pagamento ?? '')), 'UTF-8');

            if ($forma === '') {
                $forma = 'DINHEIRO';
            }

            $saldos[$origem][$forma] = ($saldos[$origem][$forma] ?? 0.0)
                + (float) $movimento->entrada
                - (float) $movimento->saida;
        }

        $ordemOrigem = [self::ORIGEM_VENDA, self::ORIGEM_RECEBIMENTO, self::ORIGEM_OUTROS];
        $resultado = [];

        foreach ($ordemOrigem as $origem) {
            if (! isset($saldos[$origem])) {
                continue;
            }

            $porForma = array_map(static fn (float $valor): float => round($valor, 2), $saldos[$origem]);

            // Dinheiro primeiro, demais formas em ordem alfabética.
            uksort($porForma, static fn (string $a, string $b): int => match (true) {
                $a === 'DINHEIRO' => -1,
                $b === 'DINHEIRO' => 1,
                default => strcmp($a, $b),
            });

            $resultado[$origem] = $porForma;
        }

        return $resultado;
    }

    /**
     * Plano dos títulos recebidos no PDV ("RECEB. CR {numero}"), só se todos tiverem o mesmo
     * plano de crédito ativo. Caso contrário fica sem plano (não há origem segura).
     *
     * @param  iterable<PdvCaixaMovimento>  $movimentos
     * @return array{id: int, nome: string}|null
     */
    private function planoDosTitulosRecebidos(iterable $movimentos, ?int $empresaId): ?array
    {
        $numeros = [];

        foreach ($movimentos as $movimento) {
            if ((string) $movimento->tipo !== 'recebimento') {
                continue;
            }

            if (preg_match('/^RECEB\.\s*CR\s+(\S+)/u', (string) $movimento->historico, $m)) {
                $numeros[] = $m[1];
            }
        }

        if ($numeros === [] || ! Schema::hasColumn((new ContaReceber)->getTable(), 'plano_conta_id')) {
            return null;
        }

        $planos = ContaReceber::query()
            ->whereIn('numero', array_values(array_unique($numeros)))
            ->when($empresaId, fn ($query) => $query->where('empresa_id', $empresaId))
            ->pluck('plano_conta_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($planos->count() !== 1 || $planos->first() <= 0) {
            return null;
        }

        $plano = PlanoConta::query()
            ->whereKey($planos->first())
            ->where('ativo', true)
            ->where('dc', 'C')
            ->first(['id', 'descricao']);

        return $plano ? [
            'id' => (int) $plano->id,
            'nome' => mb_substr(mb_strtoupper((string) $plano->descricao, 'UTF-8'), 0, 120),
        ] : null;
    }

    private function historicoFechamento(string $nome, CarbonInterface $fechamento, string $origem, string $forma): string
    {
        $historico = sprintf('FECHAMENTO DO CX:CAIXA-%s-%s', $nome, $fechamento->format('d/m/Y H:i:s'));

        $historico .= match ($origem) {
            self::ORIGEM_VENDA => ' - VENDAS',
            self::ORIGEM_RECEBIMENTO => ' - RECEB. CR',
            default => ' - OUTROS',
        };

        return $forma !== 'DINHEIRO' ? $historico.' - '.$forma : $historico;
    }

    /**
     * @param  array{id: int, nome: string}|null  $plano
     */
    private function lancar(
        string $documento,
        string $emissao,
        string $historico,
        int $contaId,
        ?int $empresaId,
        float $valor,
        ?array $plano,
    ): ?CaixaLancamento {
        $documento = mb_substr($documento, 0, 40);

        if (CaixaLancamento::query()->where('documento', $documento)->exists()) {
            return null;
        }

        $payload = [
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => $emissao,
            'documento' => $documento,
            'historico' => mb_substr($historico, 0, 180),
            'plano_contas' => $plano['nome'] ?? null,
            'plano_conta_id' => $plano['id'] ?? null,
            'caixa_conta_id' => $contaId,
            'entrada' => $valor > 0 ? round($valor, 2) : 0,
            'saida' => $valor < 0 ? round(abs($valor), 2) : 0,
        ];

        if ($empresaId && Schema::hasColumn((new CaixaLancamento)->getTable(), 'empresa_id')) {
            $payload['empresa_id'] = $empresaId;
        }

        return CaixaLancamento::query()->create($payload);
    }

    /**
     * Vendas mantêm o documento original (PDV-CX-{s} / PDV-CX-{s}-FORMA); demais origens
     * levam prefixo próprio, que nunca colide com o sufixo de forma (sem hífen).
     */
    private function documento(PdvCaixaSessao $sessao, string $origem, string $forma): string
    {
        $base = 'PDV-CX-'.$sessao->id;
        $sufixo = preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(Str::ascii($forma), 'UTF-8')) ?: 'OUTROS';

        $documento = match ($origem) {
            self::ORIGEM_VENDA => $forma === 'DINHEIRO' ? $base : $base.'-'.$sufixo,
            self::ORIGEM_RECEBIMENTO => $base.'-RC-'.$sufixo,
            default => $base.'-OU-'.$sufixo,
        };

        return mb_substr($documento, 0, 40);
    }
}
