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
 * Um único lançamento no CAIXA GERAL com o total transferível da sessão (PDV-CX-{s}),
 * sem separar meios de pagamento. Idempotente por `documento`. Sessões fechadas no
 * modelo anterior (PDV-CX-{s}-FORMA / -RC- / -SG / -SD-) são preservadas.
 */
final class PdvCaixaFechamentoService
{
    private const TIPOS_FORA_DA_TRANSFERENCIA = ['abertura', 'suprimento', 'sangria'];

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
        $operador = $this->nomeOperador($usuario, $nome);
        $abertura = $sessao->aberto_em
            ? ErpTimezone::toLocal($sessao->aberto_em)
            : null;
        $fechamento = $sessao->fechado_em
            ? ErpTimezone::toLocal($sessao->fechado_em)
            : ErpTimezone::toLocal();
        $empresaId = $sessao->empresa_id ? (int) $sessao->empresa_id : ErpContext::currentEmpresaId();

        $movimentos = $sessao->movimentos()->orderBy('id')->get();
        $saldo = $this->saldoTransferivel($movimentos);

        if (abs($saldo) < 0.005) {
            return [];
        }

        $lancamento = $this->lancar(
            documento: 'PDV-CX-'.$sessao->id,
            emissao: $fechamento->toDateString(),
            historico: $this->historicoFechamento($nome, $operador, $abertura, $fechamento),
            contaId: (int) CaixaConta::ensureCaixaGeral()->id,
            empresaId: $empresaId,
            valor: $saldo,
            plano: $this->planoDoFechamento($movimentos, $empresaId),
        );

        return $lancamento ? [$lancamento] : [];
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
     * Total líquido transferível da sessão (só movimentos de caixa já na sessão).
     *
     * Abertura e suprimento ficam fora: são fundo de troco que não gera saída em nenhuma
     * conta do Livro Caixa; transferi-los inflaria o CAIXA GERAL a cada fechamento.
     * Sangria também fica fora: é só retirada da gaveta do Caixa PDV; o dinheiro retirado
     * faz parte das vendas/recebimentos que vão integrais ao CAIXA GERAL aqui.
     *
     * @param  iterable<PdvCaixaMovimento>  $movimentos
     */
    private function saldoTransferivel(iterable $movimentos): float
    {
        $saldo = 0.0;

        foreach ($movimentos as $movimento) {
            if (in_array((string) $movimento->tipo, self::TIPOS_FORA_DA_TRANSFERENCIA, true)) {
                continue;
            }

            $saldo += (float) $movimento->entrada - (float) $movimento->saida;
        }

        return round($saldo, 2);
    }

    /**
     * Plano do lançamento único: prioriza plano de venda; se só houver recebimentos de CR,
     * usa o plano dos títulos quando for único e ativo.
     *
     * @param  iterable<PdvCaixaMovimento>  $movimentos
     * @return array{id: int, nome: string}|null
     */
    private function planoDoFechamento(iterable $movimentos, ?int $empresaId): ?array
    {
        $temVenda = false;
        $temRecebimento = false;

        foreach ($movimentos as $movimento) {
            $tipo = (string) $movimento->tipo;

            if (in_array($tipo, self::TIPOS_FORA_DA_TRANSFERENCIA, true)) {
                continue;
            }

            if (in_array($tipo, ['venda', 'estorno'], true)) {
                $temVenda = true;
            } elseif ($tipo === 'recebimento') {
                $temRecebimento = true;
            }
        }

        if ($temVenda) {
            return EmpresaParametros::planoVendaLancamento($empresaId);
        }

        if ($temRecebimento) {
            return $this->planoDosTitulosRecebidos($movimentos, $empresaId);
        }

        return null;
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

    private function nomeOperador(?User $usuario, string $usuarioFallback): string
    {
        if (! $usuario) {
            return $usuarioFallback;
        }

        $vendedor = $usuario->relationLoaded('vendedor')
            ? $usuario->vendedor
            : $usuario->vendedor()->first();

        $operador = mb_strtoupper(trim((string) ($vendedor?->nome ?? '')), 'UTF-8');

        return $operador !== '' ? $operador : $usuarioFallback;
    }

    private function historicoFechamento(
        string $usuario,
        string $operador,
        ?CarbonInterface $abertura,
        CarbonInterface $fechamento,
    ): string {
        $partes = [
            'FECHAMENTO DO CX:CAIXA-'.$usuario,
            'OP:'.$operador,
        ];

        if ($abertura) {
            $partes[] = 'AB:'.$abertura->format('d/m/Y H:i:s');
        }

        $partes[] = 'FE:'.$fechamento->format('d/m/Y H:i:s');

        return implode(' ', $partes);
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
}
